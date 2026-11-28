<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DocumentStatus;
use App\Enums\PhotoKind;
use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\DuplicateAttempt;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Models\UnitReview;
use App\Support\Accounts\Links;
use App\Support\Dashboard\Text;
use App\Support\Review\DocumentAccess;
use App\Support\Review\FieldDisplay;
use App\Support\Review\ReviewActions;
use App\Support\Review\UnitHistory;
use App\Support\Seller\StepRules;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Units review (PLAN 1.3, 2.4, 2.7; part 7.2). Both staff roles (can:review-units) see the queue and a unit's detail,
 * review documents and photos, correct figures with a reason (G3), map "other" places (G16), request changes, reject
 * and publish. Publishing as verified and turning "verified" on or off are admin only (G1), on their own routes.
 * Drafts never reach the dashboard: they are the seller's work in progress.
 */
class UnitsController extends Controller
{
    private const TZ = 'Africa/Cairo';

    /** States the queue can filter on (every state but draft). */
    private const STATES = ['submitted', 'changes_requested', 'published', 'reserved', 'sold', 'rejected', 'withdrawn'];

    public function index(Request $request): Response
    {
        $table = TableQuery::for($request)
            ->sortable(['submitted_at', 'published_at', 'serial'], default: 'submitted_at', direction: 'desc', tiebreaker: 'id')
            ->filterable(['state' => self::STATES, 'flag' => ['withdraw_request', 'verified', 'deal_tag']])
            ->virtual(['flag'])
            ->perPage(default: 25, max: 100);

        $query = Unit::query()->where('state', '!=', UnitState::Draft->value)->with(['compound', 'area', 'seller']);
        $search = $table->resolvedSearch();
        if ($search !== null) {
            self::search($query, $search);
        }
        $flag = $table->resolvedFilters()['flag'] ?? null;
        match ($flag) {
            'withdraw_request' => $query->whereNotNull('withdraw_requested_at'),
            'verified' => $query->whereNotNull('verified_at'),
            'deal_tag' => $query->where('deal_tag', true),
            default => null,
        };
        $locale = app()->getLocale();

        $map = static function (object $unit) use ($locale): array {
            if (! $unit instanceof Unit) {
                return [];
            }

            return [
                'id' => $unit->id,
                'reference' => $unit->serial !== null ? sprintf('#%04d', $unit->serial) : null,
                'code' => $unit->code,
                'title' => trim(($unit->sub_type?->label() ?? '').' · '.($unit->compound?->nameFor($locale) ?? $unit->compound_other ?? ''), ' ·'),
                'area' => $unit->area?->nameFor($locale) ?? $unit->area_other,
                'seller' => $unit->seller?->name,
                'state' => $unit->state->value,
                'state_label' => $unit->state->label(),
                'submitted_at' => $unit->submitted_at?->timezone(self::TZ)->format('Y-m-d H:i'),
                'verified' => $unit->isVerified(),
                'deal_tag' => $unit->deal_tag,
                'withdraw_requested' => $unit->withdraw_requested_at !== null,
                'has_other' => $unit->developer_id === null || $unit->compound_id === null || $unit->area_id === null,
            ];
        };

        $counts = Unit::query()->selectRaw('state, count(*) as total')->where('state', '!=', UnitState::Draft->value)->groupBy('state')->pluck('total', 'state');
        $count = static function (string $state) use ($counts): int {
            $value = $counts->get($state, 0);

            return is_numeric($value) ? (int) $value : 0;
        };

        return Inertia::render('Units/Index', [
            'table' => $table->paginate($query, $map),
            'states' => array_map(static fn (string $s): array => ['value' => $s, 'label' => UnitState::from($s)->label(), 'count' => $count($s)], self::STATES),
            'withdrawRequests' => Unit::query()->whereNotNull('withdraw_requested_at')->count(),
        ]);
    }

    public function show(Request $request, Unit $unit, ReviewActions $actions, StepRules $rules): Response
    {
        self::visible($unit);
        $me = self::me($request);
        $locale = app()->getLocale();
        $unit->load(['seller', 'developer', 'compound', 'area.parent', 'features', 'documents', 'photos']);

        $fields = [];
        $editable = match ($unit->state) {
            UnitState::Submitted => ReviewActions::TEAM_FIELDS,
            UnitState::Published, UnitState::Reserved => ReviewActions::PUBLISHED_TEAM_FIELDS,
            default => [],
        };
        foreach (['size_m2', 'garden_m2', 'roof_m2', 'floor', 'bedrooms', 'bathrooms', 'finishing', 'delivery_status', 'delivery_year', 'delivery_month', 'total_price', 'paid_amount', 'market_price_estimate', 'installment_amount', 'installment_frequency', 'next_due_date', 'years_left', 'annual_extra_amount', 'delivery_payment', 'accepts_less', 'lowest_accepted_amount', 'has_overdue', 'overdue_count', 'overdue_amount', 'overdue_settlement', 'maintenance_due', 'transfer_fee_bp', 'transfer_fee_fixed', 'description'] as $field) {
            $raw = $unit->getAttribute($field);
            $fields[] = [
                'key' => $field,
                'label' => FieldDisplay::label($field),
                'type' => FieldDisplay::type($field),
                'raw' => $raw instanceof \BackedEnum ? $raw->value : ($raw instanceof \DateTimeInterface ? $raw->format('Y-m-d') : $raw),
                'display' => FieldDisplay::value($field, $raw),
                'options' => FieldDisplay::options($field),
                'editable' => in_array($field, $editable, true),
                'group' => match (true) {
                    in_array($field, ['size_m2', 'garden_m2', 'roof_m2', 'floor', 'bedrooms', 'bathrooms', 'finishing', 'delivery_status', 'delivery_year', 'delivery_month'], true) => 'unit',
                    $field === 'description' => 'description',
                    default => 'money',
                },
            ];
        }

        $documents = $unit->documents->sortBy('id')->values()->map(static function (UnitDocument $d) use ($me, $unit): array {
            $mayOpen = DocumentAccess::mayOpen($me, $d->setRelation('unit', $unit));

            return [
                'id' => $d->id,
                'type' => $d->type->value,
                'type_label' => $d->type->label(),
                'is_national_id' => $d->type->isNationalId(),
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'note' => $d->review_note,
                'mime' => $d->mime,
                'bytes' => $d->bytes,
                'flags' => is_array($d->flags) ? array_values(array_filter($d->flags, is_string(...))) : [],
                'uploaded_at' => $d->created_at?->timezone(self::TZ)->format('Y-m-d H:i'),
                'reviewed_at' => $d->reviewed_at?->timezone(self::TZ)->format('Y-m-d H:i'),
                'purged' => $d->purged_at !== null,
                'url' => $mayOpen ? route('dashboard.files.document', ['document' => $d->public_id], false) : null,
            ];
        })->all();

        $photos = $unit->photos->map(static fn (UnitPhoto $p): array => [
            'id' => $p->id,
            'kind' => $p->kind->value,
            'url' => route('dashboard.files.photo', ['photo' => $p->public_id], false),
            'is_hidden' => $p->is_hidden,
            'hidden_reason' => $p->hidden_reason,
            'is_project_image' => $p->is_project_image,
            'is_public' => is_string($p->public_path),
        ])->values()->all();

        $duplicates = DuplicateAttempt::query()->where(fn ($q) => $q->where('unit_id', $unit->id)->orWhere('matched_unit_id', $unit->id))
            ->with(['matchedUnit:id,serial,code'])->latest('id')->limit(20)->get()
            ->map(static fn (DuplicateAttempt $a): array => [
                'at' => $a->created_at->timezone(self::TZ)->format('Y-m-d H:i'),
                'kind' => $a->key_kind,
                'stage' => $a->stage,
                'blocked' => (bool) $a->blocked,
                'other' => $a->unit_id === $unit->id ? self::ref($a->matchedUnit) : ($a->unit_id !== null ? self::ref(Unit::query()->find($a->unit_id, ['id', 'serial', 'code'])) : null),
                'other_id' => $a->unit_id === $unit->id ? $a->matched_unit_id : $a->unit_id,
            ])->values()->all();

        $last = $unit->reviews()->latest('id')->first();

        return Inertia::render('Units/Show', [
            'unit' => [
                'id' => $unit->id,
                'reference' => $unit->serial !== null ? sprintf('#%04d', $unit->serial) : null,
                'code' => $unit->code,
                'state' => $unit->state->value,
                'state_label' => $unit->state->label(),
                'main_type' => $unit->main_type?->label(),
                'sub_type' => $unit->sub_type?->label(),
                'contract_number' => $unit->contract_number,
                'unit_number' => $unit->unit_number,
                'contract_date' => $unit->contract_year !== null ? sprintf('%02d.%d', (int) ($unit->contract_month ?? 0), $unit->contract_year) : null,
                'facade' => self::sides($unit->facade),
                'features' => $unit->features->map(fn ($f): string => $f->nameFor($locale))->values()->all(),
                'submitted_at' => $unit->submitted_at?->timezone(self::TZ)->format('Y-m-d H:i'),
                'published_at' => $unit->published_at?->timezone(self::TZ)->format('Y-m-d H:i'),
                'verified' => $unit->isVerified(),
                'deal_tag' => $unit->deal_tag,
                'withdraw_requested' => $unit->withdraw_requested_at !== null,
                'withdraw_reason' => $unit->withdraw_reason,
                'pending_change' => $unit->pendingChanges()->where('state', 'pending')->exists(),
                'recheck' => $unit->recheck_after_edit_at !== null && $unit->state->isPublic(),
                'public_url' => $unit->state->isPublic() && is_string($unit->slug) ? Links::frontend('/units/'.$unit->slug, $locale) : null,
                'warnings' => $rules->warnings($unit),
            ],
            'seller' => $unit->seller !== null ? [
                'name' => $unit->seller->name,
                'email' => $unit->seller->email,
                'phone' => $unit->seller->phone,
                'email_verified' => $unit->seller->hasVerifiedEmail(),
                'units' => Unit::query()->where('seller_id', $unit->seller_id)->where('state', '!=', UnitState::Draft->value)->count(),
            ] : null,
            'places' => [
                'developer' => ['id' => $unit->developer_id, 'name' => $unit->developer?->nameFor($locale), 'other' => $unit->developer_other],
                'compound' => ['id' => $unit->compound_id, 'name' => $unit->compound?->nameFor($locale), 'other' => $unit->compound_other],
                'area' => ['id' => $unit->area_id, 'name' => $unit->area !== null ? trim(($unit->area->parent?->nameFor($locale) ?? '').' / '.$unit->area->nameFor($locale), ' /') : null, 'other' => $unit->area_other],
                'mappable' => $unit->state === UnitState::Submitted,
            ],
            'choices' => $unit->state === UnitState::Submitted ? self::choices($locale) : null,
            'fields' => $fields,
            'documents' => $documents,
            'photos' => $photos,
            'duplicates' => $duplicates,
            'lastReview' => $last instanceof UnitReview ? [
                'outcome' => $last->outcome->label(),
                'reason' => $last->reason,
                'fields' => self::reviewNotes($last),
                'at' => $last->created_at->timezone(self::TZ)->format('Y-m-d H:i'),
            ] : null,
            'blockers' => $actions->publishBlockers($unit),
            'noteFields' => array_map(static fn (string $f): array => ['value' => $f, 'label' => FieldDisplay::label($f)], ReviewActions::NOTE_FIELDS),
            'history' => UnitHistory::rows($unit),
            'can' => [
                'decide' => $unit->state === UnitState::Submitted,
                'reject' => in_array($unit->state, [UnitState::Submitted, UnitState::ChangesRequested], true),
                'verify' => $me->isAdmin(),
                'flags' => $unit->state->isPublic(),
                'withdrawal' => in_array($unit->state, [UnitState::Reserved, UnitState::Published], true) && $unit->withdraw_requested_at !== null,
                'documents' => $unit->state !== UnitState::Draft,
            ],
        ]);
    }

    public function requestChanges(Request $request, Unit $unit, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'fields' => ['nullable', 'array', 'max:20'],
            'fields.*' => ['nullable', 'string', 'max:300'],
        ]);
        $notes = [];
        $fields = $request->input('fields');
        foreach (is_array($fields) ? $fields : [] as $field => $note) {
            if (is_string($field) && is_string($note)) {
                $notes[$field] = $note;
            }
        }
        $actions->requestChanges($unit, self::me($request), trim($request->string('reason')->toString()), $notes);

        return back()->with('status', Text::get('units.done.changes_requested'));
    }

    public function reject(Request $request, Unit $unit, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $actions->reject($unit, self::me($request), trim($request->string('reason')->toString()));

        return back()->with('status', Text::get('units.done.rejected'));
    }

    public function publish(Request $request, Unit $unit, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        $actions->publish($unit, self::me($request), false);

        return back()->with('status', Text::get('units.done.published', ['code' => is_string($unit->code) ? $unit->code : '']));
    }

    /** Publish as verified (G1): admin only, on its own route (can:verify-units). */
    public function publishVerified(Request $request, Unit $unit, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        $actions->publish($unit, self::me($request), true);

        return back()->with('status', Text::get('units.done.published', ['code' => is_string($unit->code) ? $unit->code : '']));
    }

    /** "Verified" on or off later (G1): admin only (can:verify-units), with a reason. */
    public function verified(Request $request, Unit $unit, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        $request->validate(['verified' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $actions->setVerified($unit, self::me($request), $request->boolean('verified'), trim($request->string('reason')->toString()));

        return back()->with('status', Text::get('staff.saved'));
    }

    public function dealTag(Request $request, Unit $unit, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        $request->validate(['deal_tag' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:500']]);
        $reason = $request->string('reason')->trim()->toString();
        $actions->setDealTag($unit, $request->boolean('deal_tag'), $reason !== '' ? $reason : null);

        return back()->with('status', Text::get('staff.saved'));
    }

    public function fields(Request $request, Unit $unit, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500'], 'values' => ['required', 'array', 'min:1', 'max:40']]);
        $values = [];
        foreach ((array) $request->input('values') as $field => $value) {
            if (is_string($field)) {
                $values[$field] = $value;
            }
        }
        $changed = $actions->teamEdit($unit, trim($request->string('reason')->toString()), $values);

        return back()->with('status', $changed === [] ? Text::get('units.done.nothing_changed') : Text::get('units.done.fields_saved'));
    }

    public function places(Request $request, Unit $unit, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        $request->validate([
            'developer_id' => ['nullable', 'integer'],
            'compound_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
        ]);
        $id = static function (string $key) use ($request): ?int {
            $value = $request->input($key);

            return is_numeric($value) ? (int) $value : null;
        };
        $actions->mapPlaces($unit, $id('developer_id'), $id('compound_id'), $id('area_id'));

        return back()->with('status', Text::get('staff.saved'));
    }

    public function document(Request $request, Unit $unit, UnitDocument $document, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        abort_unless($document->unit_id === $unit->id, 404);
        $request->validate(['status' => ['required', Rule::in([DocumentStatus::Approved->value, DocumentStatus::NeedsReplacement->value])], 'note' => ['nullable', 'string', 'max:500']]);
        $note = $request->input('note');
        $actions->reviewDocument($document, self::me($request), DocumentStatus::from($request->string('status')->toString()), is_string($note) ? $note : null);

        return back()->with('status', Text::get('staff.saved'));
    }

    public function photo(Request $request, Unit $unit, UnitPhoto $photo, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        abort_unless($photo->unit_id === $unit->id, 404);
        $request->validate(['hidden' => ['sometimes', 'boolean'], 'project_image' => ['sometimes', 'boolean'], 'reason' => ['nullable', 'string', 'max:500']]);
        if ($request->has('hidden')) {
            $reason = $request->input('reason');
            $actions->setPhotoHidden($photo, self::me($request), $request->boolean('hidden'), is_string($reason) ? $reason : null);
        }
        if ($request->has('project_image') && $photo->kind === PhotoKind::Photo) {
            $actions->setProjectImage($photo, $request->boolean('project_image'));
        }

        return back()->with('status', Text::get('staff.saved'));
    }

    public function withdrawal(Request $request, Unit $unit, ReviewActions $actions): RedirectResponse
    {
        self::visible($unit);
        $request->validate(['approve' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:500']]);
        $reason = $request->input('reason');
        $actions->decideWithdrawal($unit, self::me($request), $request->boolean('approve'), is_string($reason) && trim($reason) !== '' ? trim($reason) : null);

        return back()->with('status', Text::get('staff.saved'));
    }

    /**
     * Search by code, reference number (#0124), compound name, or the seller's name, e-mail or mobile.
     *
     * @param  Builder<Unit>  $query
     */
    private static function search(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';
        $digits = ltrim($term, '#');
        $query->where(static function (Builder $q) use ($like, $digits): void {
            $q->where('code', 'like', $like)
                ->orWhereHas('compound', static function (Builder $c) use ($like): void {
                    $c->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
                })
                ->orWhereHas('seller', static function (Builder $s) use ($like): void {
                    $s->where('email', 'like', $like)->orWhere('name', 'like', $like)->orWhere('phone', 'like', $like);
                });
            if ($digits !== '' && ctype_digit($digits)) {
                $q->orWhere('serial', (int) $digits);
            }
        });
    }

    /**
     * @return list<array{label: string, note: string}>
     */
    private static function reviewNotes(UnitReview $review): array
    {
        $notes = $review->notes ?? [];
        $out = [];
        foreach (is_array($notes['fields'] ?? null) ? $notes['fields'] : [] as $field => $note) {
            if (is_string($note)) {
                $out[] = ['label' => FieldDisplay::label((string) $field), 'note' => $note];
            }
        }

        return $out;
    }

    /** Drafts are the seller's own work in progress: never opened from the dashboard. */
    private static function visible(Unit $unit): void
    {
        abort_if($unit->state === UnitState::Draft, 404);
    }

    private static function me(Request $request): AdminUser
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof AdminUser, 403);

        return $admin;
    }

    private static function ref(?Unit $unit): ?string
    {
        if ($unit === null) {
            return null;
        }

        return $unit->code ?? ($unit->serial !== null ? sprintf('#%04d', $unit->serial) : '#'.$unit->id);
    }

    /**
     * @return list<string>
     */
    private static function sides(?int $bits): array
    {
        $out = [];
        foreach (['north' => 1, 'east' => 2, 'south' => 4, 'west' => 8] as $side => $bit) {
            if ($bits !== null && ($bits & $bit) !== 0) {
                $out[] = Text::get("units.sides.{$side}");
            }
        }

        return $out;
    }

    /**
     * The managed lists for mapping a seller's "other" entry (G16). Governorates are listed one under the other (K1).
     *
     * @return array{developers: list<array{value: string, label: string}>, compounds: list<array{value: string, label: string, developer: int}>, areas: list<array{value: string, label: string}>}
     */
    private static function choices(string $locale): array
    {
        $areas = [];
        $all = Area::query()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
        $byParent = [];
        foreach ($all as $area) {
            $byParent[$area->parent_id ?? 0][] = $area;
        }
        $walk = static function (int $parent, string $prefix) use (&$walk, $byParent, $locale, &$areas): void {
            foreach ($byParent[$parent] ?? [] as $area) {
                $label = $prefix.$area->nameFor($locale);
                if ($parent !== 0) {
                    $areas[] = ['value' => (string) $area->id, 'label' => $label];
                }
                $walk($area->id, $label.' / ');
            }
        };
        $walk(0, '');

        $developers = [];
        foreach (Developer::query()->where('is_active', true)->get() as $developer) {
            $developers[] = ['value' => (string) $developer->id, 'label' => $developer->nameFor($locale)];
        }
        $compounds = [];
        foreach (Compound::query()->where('is_active', true)->get() as $compound) {
            $compounds[] = ['value' => (string) $compound->id, 'label' => $compound->nameFor($locale), 'developer' => $compound->developer_id];
        }
        usort($developers, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
        usort($compounds, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return ['developers' => $developers, 'compounds' => $compounds, 'areas' => $areas];
    }
}
