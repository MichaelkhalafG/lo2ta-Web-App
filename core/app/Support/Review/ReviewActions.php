<?php

namespace App\Support\Review;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PhotoKind;
use App\Enums\ReviewOutcome;
use App\Enums\UnitState;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Deal;
use App\Models\Developer;
use App\Models\ExitReport;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Support\Accounts\Links;
use App\Support\Activity\ActivityLogger;
use App\Support\Calculator\ExitReportPdf;
use App\Support\Dashboard\Text;
use App\Support\Deals\DealFlow;
use App\Support\Listing\UnitCode;
use App\Support\Mail\Outbox;
use App\Support\Seller\ContractKeys;
use App\Support\Seller\LiveEdits;
use App\Support\Seller\StepRules;
use App\Support\Settings\Settings;
use App\Support\Uploads\PhotoPublisher;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The staff side of a unit (PLAN 2.2.6, 2.4, 2.7; part 7.2): the four review outcomes, "verified" on and off (G1, admin
 * only), the deal tag, document and photo review, figures the team corrects (G3, with a reason the seller sees),
 * mapping the seller's "other" developer, compound or area (G16), and the seller's withdrawal request during a deal.
 * Every action checks the unit's state here, on the server, and writes one named line to the activity log with who,
 * before and after. E-mails go to the seller only and never carry staff names.
 */
final class ReviewActions
{
    /** Figures and specs the team may correct while the unit is under review (G3). */
    public const TEAM_FIELDS = [...LiveEdits::REVIEW, 'total_price', 'description'];

    /** After publishing, the team corrects only the market estimate here (PLAN 2.2.6); other changes are part 7.3. */
    public const PUBLISHED_TEAM_FIELDS = ['market_price_estimate'];

    /** Fields a "request changes" note may point at. */
    public const NOTE_FIELDS = [
        'developer', 'compound', 'area', 'main_type', 'sub_type', 'contract_number', 'unit_number', 'contract_year',
        ...self::TEAM_FIELDS, 'facade', 'features', 'photos',
    ];

    private const STEP2 = ['size_m2', 'garden_m2', 'roof_m2', 'floor', 'bedrooms', 'bathrooms', 'finishing', 'delivery_status', 'delivery_year', 'delivery_month'];

    public function __construct(
        private readonly StepRules $rules,
        private readonly ActivityLogger $log,
        private readonly Outbox $outbox,
        private readonly PhotoPublisher $photos,
        private readonly ExitReportPdf $pdfs,
        private readonly Settings $settings,
    ) {}

    /**
     * What still stands between the unit and publishing, in words; [] when it can be published.
     *
     * @return list<string>
     */
    public function publishBlockers(Unit $unit): array
    {
        if ($unit->state !== UnitState::Submitted) {
            return [Text::get('units.blockers.state')];
        }
        $out = [];
        foreach (['developer' => $unit->developer_id, 'compound' => $unit->compound_id, 'area' => $unit->area_id] as $place => $id) {
            if ($id === null) {
                $out[] = Text::get("units.blockers.map_{$place}");
            }
        }
        if ($unit->area_id !== null) {
            try {
                UnitCode::code($unit);
            } catch (RuntimeException) {
                $out[] = Text::get('units.blockers.area_code');
            }
        }
        foreach ([1, 2, 3, 5] as $step) {
            if ($this->rules->problems($step, $unit) !== []) {
                $out[] = Text::get('units.blockers.step', ['step' => $step]);
            }
        }
        $documents = $unit->documents()->whereNull('purged_at')->get();
        foreach ($this->requiredDocuments() as $type) {
            if (! $documents->contains(fn (UnitDocument $d): bool => $d->type === $type && $d->status === DocumentStatus::Approved)) {
                $out[] = Text::get('units.blockers.document', ['type' => $type->label()]);
            }
        }
        if ($documents->contains(fn (UnitDocument $d): bool => $d->status === DocumentStatus::Uploaded)) {
            $out[] = Text::get('units.blockers.unreviewed');
        }
        if ($documents->contains(fn (UnitDocument $d): bool => $d->status === DocumentStatus::NeedsReplacement)) {
            $out[] = Text::get('units.blockers.replacement');
        }
        if (! $unit->photos()->where('kind', PhotoKind::Photo->value)->where('is_hidden', false)->exists()) {
            $out[] = Text::get('units.blockers.photo');
        }

        return $out;
    }

    /**
     * @return list<DocumentType>
     */
    public function requiredDocuments(): array
    {
        $needed = [DocumentType::Contract, DocumentType::Schedule, DocumentType::NationalIdFront, DocumentType::NationalIdBack];
        if ($this->settings->bool('receipts_required')) {
            $needed[] = DocumentType::Receipt;
        }

        return $needed;
    }

    /**
     * The unit goes back to the seller with a reason, notes per field, and the documents marked for replacement.
     *
     * @param  array<string, string>  $fieldNotes
     */
    public function requestChanges(Unit $unit, AdminUser $by, string $reason, array $fieldNotes): void
    {
        self::mustBe($unit, [UnitState::Submitted]);
        $notes = [];
        foreach ($fieldNotes as $field => $note) {
            $note = trim($note);
            if (in_array($field, self::NOTE_FIELDS, true) && $note !== '') {
                $notes[$field] = mb_substr($note, 0, 300);
            }
        }
        $documents = [];
        foreach ($unit->documents()->where('status', DocumentStatus::NeedsReplacement->value)->whereNull('purged_at')->get() as $document) {
            $documents[] = ['type' => $document->type->value, 'note' => $document->review_note];
        }

        DB::transaction(function () use ($unit, $by, $reason, $notes, $documents): void {
            $unit->reviews()->create(['admin_id' => $by->id, 'outcome' => ReviewOutcome::ChangesRequested, 'reason' => $reason, 'notes' => ['fields' => $notes, 'documents' => $documents]]);
            $unit->forceFill(['state' => UnitState::ChangesRequested])->save();
        });
        $this->log->record('unit.changes_requested', $unit, ['state' => UnitState::Submitted->value], ['state' => UnitState::ChangesRequested->value, 'fields' => $notes, 'documents' => $documents], $reason);
        $this->mailSeller($unit, 'seller.unit_changes_requested', [
            'kind' => 'changes_requested', 'reason' => $reason,
            'fields' => array_map(static fn (string $f, string $n): array => ['field' => $f, 'note' => $n], array_keys($notes), $notes),
            'documents' => $documents,
        ]);
    }

    /** Rejected: off the review queue, the contract keys released, the reason e-mailed (PLAN 2.2.6). */
    public function reject(Unit $unit, AdminUser $by, string $reason): void
    {
        $from = $unit->state;
        self::mustBe($unit, [UnitState::Submitted, UnitState::ChangesRequested]);
        DB::transaction(function () use ($unit, $by, $reason): void {
            $unit->reviews()->create(['admin_id' => $by->id, 'outcome' => ReviewOutcome::Rejected, 'reason' => $reason, 'notes' => null]);
            $unit->forceFill(['state' => UnitState::Rejected, 'rejected_at' => now(), 'active_contract_key' => null, 'active_unit_key' => null])->save();
        });
        $this->log->record('unit.rejected', $unit, ['state' => $from->value], ['state' => UnitState::Rejected->value], $reason);
        $this->mailSeller($unit, 'seller.unit_rejected', ['kind' => 'rejected', 'reason' => $reason]);
    }

    /**
     * Publish, with or without the verified badge (G1: verified is for an admin only). The public code and slug are
     * formed now (PLAN 2.2.8); the photos get their public copies; the "after review" exit report is generated in both
     * languages and the seller's copy goes with the e-mail (G19).
     */
    public function publish(Unit $unit, AdminUser $by, bool $verified): void
    {
        if ($verified && ! $by->isAdmin()) {
            throw new AuthorizationException;
        }
        $blockers = $this->publishBlockers($unit);
        if ($blockers !== []) {
            throw ValidationException::withMessages(['publish' => $blockers[0]]);
        }

        DB::transaction(function () use ($unit, $by, $verified): void {
            UnitCode::assign($unit);
            $unit->forceFill([
                'state' => UnitState::Published,
                'published_at' => now(),
                'verified_at' => $verified ? now() : null,
                'verified_by' => $verified ? $by->id : null,
            ])->save();
            $unit->reviews()->create(['admin_id' => $by->id, 'outcome' => $verified ? ReviewOutcome::PublishedVerified : ReviewOutcome::Published, 'reason' => null, 'notes' => null]);
        });
        foreach ($unit->photos()->get() as $photo) {
            $this->photos->publish($photo);
        }
        $this->log->record('unit.published', $unit, ['state' => UnitState::Submitted->value], ['state' => UnitState::Published->value, 'code' => $unit->code, 'verified' => $verified]);

        $seller = $unit->seller;
        $attach = null;
        foreach (['ar', 'en'] as $language) {
            try {
                $report = $this->pdfs->generate($unit, 'verified', $language);
                if ($report instanceof ExitReport && $seller !== null && $language === $seller->preferredLocale()) {
                    $attach = $report->id;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
        $this->mailSeller($unit, 'seller.unit_published', ['kind' => $verified ? 'published_verified' : 'published', 'exit_report_id' => $attach, 'unit_path' => '/units/'.$unit->slug]);
    }

    /** "Verified" on or off later (G1, PLAN 2.2.6): admin only, with a reason; who and when are never public. */
    public function setVerified(Unit $unit, AdminUser $by, bool $on, string $reason): void
    {
        if (! $by->isAdmin()) {
            throw new AuthorizationException;
        }
        self::mustBe($unit, [UnitState::Published, UnitState::Reserved]);
        if ($unit->isVerified() === $on) {
            return;
        }
        $before = ['verified' => $unit->isVerified()];
        $unit->forceFill(['verified_at' => $on ? now() : null, 'verified_by' => $on ? $by->id : null])->save();
        $this->log->record($on ? 'unit.verified' : 'unit.unverified', $unit, $before, ['verified' => $on], $reason);
        if ($on) {
            $this->mailSeller($unit, 'seller.unit_verified', ['kind' => 'verified', 'unit_path' => '/units/'.$unit->slug]);
        }
    }

    /** The "لقطة" deal tag on a public unit (PLAN 2.2.6, Q21); logged. */
    public function setDealTag(Unit $unit, bool $on, ?string $reason): void
    {
        self::mustBe($unit, [UnitState::Published, UnitState::Reserved]);
        if ($unit->deal_tag === $on) {
            return;
        }
        $unit->forceFill(['deal_tag' => $on])->save();
        $this->log->record($on ? 'unit.deal_tag_set' : 'unit.deal_tag_cleared', $unit, ['deal_tag' => ! $on], ['deal_tag' => $on], $reason);
    }

    /** One document approved, or marked for a clearer copy with a note the seller reads (PLAN 2.4). */
    public function reviewDocument(UnitDocument $document, AdminUser $by, DocumentStatus $status, ?string $note): void
    {
        $unit = $document->unit;
        if ($unit === null || $unit->state === UnitState::Draft || $document->purged_at !== null) {
            throw ValidationException::withMessages(['document' => Text::get('units.errors.document_state')]);
        }
        $note = $status === DocumentStatus::NeedsReplacement ? mb_substr(trim((string) $note), 0, 500) : null;
        if ($status === DocumentStatus::NeedsReplacement && mb_strlen((string) $note) < 3) {
            throw ValidationException::withMessages(['note' => Text::get('units.errors.note_required')]);
        }
        $before = ['document' => $document->public_id, 'type' => $document->type->value, 'status' => $document->status->value, 'note' => $document->review_note];
        $document->forceFill(['status' => $status, 'review_note' => $note, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
        $this->log->record('document.reviewed', $unit, $before, ['document' => $document->public_id, 'type' => $document->type->value, 'status' => $status->value, 'note' => $note]);
    }

    /** Hide a photo that shows a building number, a sign or a document (PLAN 2.5), or show it again; logged. */
    public function setPhotoHidden(UnitPhoto $photo, AdminUser $by, bool $hidden, ?string $reason): void
    {
        $unit = $photo->unit;
        if ($unit === null || $unit->state === UnitState::Draft) {
            throw ValidationException::withMessages(['photo' => Text::get('units.errors.photo_state')]);
        }
        $reason = $hidden ? mb_substr(trim((string) $reason), 0, 500) : null;
        if ($hidden && mb_strlen((string) $reason) < 3) {
            throw ValidationException::withMessages(['reason' => Text::get('units.errors.reason_required')]);
        }
        if ($photo->is_hidden === $hidden) {
            return;
        }
        $photo->forceFill([
            'is_hidden' => $hidden,
            'hidden_reason' => $reason,
            'hidden_by' => $hidden ? $by->id : null,
            'hidden_at' => $hidden ? now() : null,
        ])->save();
        if ($hidden) {
            $this->photos->withdraw($photo);
        } elseif ($unit->state->isPublic()) {
            $this->photos->publish($photo);
        }
        $this->log->record($hidden ? 'photo.hidden' : 'photo.shown', $unit, ['photo' => $photo->public_id, 'hidden' => ! $hidden], ['photo' => $photo->public_id, 'hidden' => $hidden], $hidden ? $reason : null);
    }

    /** The "Project image" label, set by the seller and editable by the team (PLAN 2.2.9 point 7). */
    public function setProjectImage(UnitPhoto $photo, bool $on): void
    {
        $unit = $photo->unit;
        if ($unit === null || $unit->state === UnitState::Draft || $photo->is_project_image === $on) {
            return;
        }
        $photo->forceFill(['is_project_image' => $on])->save();
        $this->log->record('photo.label_changed', $unit, ['photo' => $photo->public_id, 'project_image' => ! $on], ['photo' => $photo->public_id, 'project_image' => $on]);
    }

    /**
     * Figures the team corrects (G3): checked with the seller's own step rules on a copy first, saved together, logged
     * with the reason; the seller sees each change and its reason in My account.
     *
     * @param  array<string, mixed>  $input
     * @return list<string> the fields that changed
     */
    public function teamEdit(Unit $unit, string $reason, array $input): array
    {
        $allowed = match ($unit->state) {
            UnitState::Submitted => self::TEAM_FIELDS,
            UnitState::Published, UnitState::Reserved => self::PUBLISHED_TEAM_FIELDS,
            default => [],
        };
        $input = array_intersect_key($input, array_flip($allowed));
        if ($allowed === [] || $input === []) {
            throw ValidationException::withMessages(['fields' => Text::get('units.errors.nothing_to_edit')]);
        }

        $copy = clone $unit;
        $step2 = array_intersect_key($input, array_flip(self::STEP2));
        $step3 = array_diff_key($input, array_flip([...self::STEP2, 'description']));
        if ($step2 !== []) {
            $this->rules->apply(2, $copy, $step2);
        }
        if ($step3 !== []) {
            $this->rules->apply(3, $copy, $step3);
        }
        if (array_key_exists('description', $input)) {
            $this->rules->apply(5, $copy, ['description' => $input['description']]);
        }
        $kept = self::plain($unit->next_due_date);
        $problems = [...$this->rules->problems(2, $copy), ...$this->rules->problems(3, $copy, is_string($kept) ? $kept : null), ...$this->rules->problems(5, $copy)];
        if ($problems !== []) {
            throw ValidationException::withMessages($problems);
        }

        $before = [];
        $after = [];
        foreach (self::TEAM_FIELDS as $field) {
            $old = self::plain($unit->getAttribute($field));
            $new = self::plain($copy->getAttribute($field));
            if ($old !== $new) {
                $before[$field] = $old;
                $after[$field] = $new;
            }
        }
        if ($after === []) {
            return [];
        }
        $unit->forceFill(array_map(fn (string $f): mixed => $copy->getAttribute($f), array_combine(array_keys($after), array_keys($after))))->save();
        $this->log->record('unit.team_edit', $unit, $before, $after, $reason);

        return array_keys($after);
    }

    /**
     * Map the seller's "other" developer, compound or area to the managed lists (G16, PLAN 2.5): the free text never
     * goes public. The contract keys are worked out again; a mapping that makes the unit match another active unit is
     * refused, with the match named for the team.
     */
    public function mapPlaces(Unit $unit, ?int $developerId, ?int $compoundId, ?int $areaId): void
    {
        self::mustBe($unit, [UnitState::Submitted]);
        $developer = $developerId !== null ? Developer::query()->whereKey($developerId)->where('is_active', true)->first() : null;
        $compound = $compoundId !== null ? Compound::query()->whereKey($compoundId)->where('is_active', true)->first() : null;
        $area = $areaId !== null ? Area::query()->whereKey($areaId)->where('is_active', true)->whereNotNull('parent_id')->first() : null;
        if (($developerId !== null && $developer === null) || ($compoundId !== null && $compound === null) || ($areaId !== null && $area === null)) {
            throw ValidationException::withMessages(['places' => Text::get('units.errors.place_unknown')]);
        }
        $developerId = $developer->id ?? $unit->developer_id;
        if ($compound !== null && $developerId !== null && $compound->developer_id !== $developerId) {
            throw ValidationException::withMessages(['compound' => Text::get('units.errors.compound_developer')]);
        }

        $before = ['developer_id' => $unit->developer_id, 'compound_id' => $unit->compound_id, 'area_id' => $unit->area_id];
        $copy = clone $unit;
        $copy->forceFill(array_filter(['developer_id' => $developer?->id, 'compound_id' => $compound?->id, 'area_id' => $area?->id], static fn ($v): bool => $v !== null));
        $keys = ContractKeys::of($copy);
        if ($keys !== null) {
            $match = Unit::query()->whereKeyNot($unit->id)
                ->where(fn ($q) => $q->where('active_contract_key', $keys['contract'])->orWhere('active_unit_key', $keys['unit']))
                ->first(['id', 'serial', 'code']);
            if ($match instanceof Unit) {
                throw ValidationException::withMessages(['places' => Text::get('units.errors.place_duplicate', ['unit' => $match->code ?? sprintf('#%04d', (int) $match->serial)])]);
            }
            $copy->forceFill(['contract_key' => $keys['contract'], 'unit_key' => $keys['unit'], 'active_contract_key' => $keys['contract'], 'active_unit_key' => $keys['unit']]);
        }
        $after = ['developer_id' => $copy->developer_id, 'compound_id' => $copy->compound_id, 'area_id' => $copy->area_id];
        if ($after === $before) {
            return;
        }
        $unit->forceFill([...$after, ...array_intersect_key($copy->getAttributes(), array_flip(['contract_key', 'unit_key', 'active_contract_key', 'active_unit_key']))])->save();
        $this->log->record('unit.places_mapped', $unit, $before, $after);
    }

    /**
     * The seller asked to withdraw a unit with an open deal (PLAN 2.2.7, 2.2.9 point 5). Approved: the unit leaves the
     * market as if the seller withdrew it. Declined: the request is cleared and the seller gets the reason.
     */
    public function decideWithdrawal(Unit $unit, AdminUser $by, bool $approve, ?string $reason): void
    {
        self::mustBe($unit, [UnitState::Reserved, UnitState::Published]);
        if ($unit->withdraw_requested_at === null) {
            throw ValidationException::withMessages(['unit' => Text::get('units.errors.no_withdraw_request')]);
        }
        if (! $approve && ($reason === null || mb_strlen(trim($reason)) < 3)) {
            throw ValidationException::withMessages(['reason' => Text::get('units.errors.reason_required')]);
        }
        if (! $approve) {
            $unit->forceFill(['withdraw_requested_at' => null])->save();
            $this->log->record('unit.withdraw_declined', $unit, ['withdraw_requested' => true], ['withdraw_requested' => false], $reason);
            $this->mailSeller($unit, 'seller.withdraw_declined', ['kind' => 'withdraw_declined', 'reason' => $reason]);

            return;
        }

        // The open deal ends first (part 7.5): the seller withdrew.
        $deal = Deal::query()->where('open_unit_id', $unit->id)->first();
        if ($deal instanceof Deal) {
            app(DealFlow::class)->cancel($deal, $by, $reason ?? Text::get('deals.withdrawn_reason'));
            $unit->refresh();
        }
        DB::transaction(function () use ($unit): void {
            $unit->forceFill([
                'state' => UnitState::Withdrawn, 'withdrawn_at' => now(), 'withdraw_requested_at' => null,
                'active_contract_key' => null, 'active_unit_key' => null,
            ])->save();
            $unit->pendingChanges()->where('state', 'pending')->update(['state' => 'rejected', 'note' => 'withdrawn on request', 'reviewed_at' => now()]);
        });
        foreach ($unit->photos()->get() as $photo) {
            $this->photos->withdraw($photo);
        }
        $this->log->record('unit.withdraw_approved', $unit, ['state' => UnitState::Reserved->value], ['state' => UnitState::Withdrawn->value], $reason);
        $this->mailSeller($unit, 'seller.unit_withdrawn', ['kind' => 'withdrawn']);
    }

    /**
     * @param  list<UnitState>  $states
     */
    private static function mustBe(Unit $unit, array $states): void
    {
        if (! in_array($unit->state, $states, true)) {
            throw ValidationException::withMessages(['unit' => Text::get('units.errors.state')]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mailSeller(Unit $unit, string $template, array $payload): void
    {
        $seller = $unit->seller;
        if ($seller === null) {
            return;
        }
        $locale = $seller->preferredLocale();
        $reference = $unit->code ?? ($unit->serial !== null ? sprintf('#%04d', $unit->serial) : '');
        $path = $payload['unit_path'] ?? null;
        unset($payload['unit_path']);
        $this->outbox->send($seller->email, $seller->name, $locale, $template, [
            ...$payload,
            'reference' => $reference,
            'account_url' => Links::frontend('/account/units/'.$unit->public_id, $locale),
            'unit_url' => is_string($path) ? Links::frontend($path, $locale) : null,
        ]);
    }

    /** A value as stored: enums as codes, dates as Y-m-d, decimals as strings. */
    private static function plain(mixed $value): int|float|string|bool|null
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_int($value), is_float($value), is_string($value), is_bool($value), $value === null => $value,
            default => null,
        };
    }
}
