<?php

namespace App\Support\Review;

use App\Enums\UnitState;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\Unit;
use App\Models\UnitPendingChange;
use App\Support\Accounts\Links;
use App\Support\Activity\ActivityLogger;
use App\Support\Dashboard\Text;
use App\Support\Mail\Outbox;
use App\Support\Seller\LiveEdits;
use App\Support\Seller\StepRules;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The staff side of a published unit (PLAN 2.2.7; part 7.3): figure changes that wait for review (approved: live at
 * once and shown in My account; rejected: the seller is e-mailed the reason, G15), the "check after edit" list of free
 * edits (a description can be put back from the log; a changed photo can only be hidden, G17), and documents a seller
 * adds after publishing. Every action is logged with who, before and after.
 */
final class AfterPublish
{
    /** Seller actions that put a live unit on the "check after edit" list. */
    public const FREE_EDITS = ['unit.live_edit', 'photo.uploaded', 'photo.deleted', 'photo.reordered'];

    public function __construct(
        private readonly StepRules $rules,
        private readonly ActivityLogger $log,
        private readonly Outbox $outbox,
    ) {}

    /**
     * Approve a pending figure change: the values go live together, after the seller's own step rules pass on the
     * result (the live unit may have moved since the seller sent it).
     */
    public function approve(UnitPendingChange $change, AdminUser $by): void
    {
        $unit = self::openUnit($change);
        $copy = clone $unit;
        $before = [];
        $after = [];
        foreach ($change->changes as $field => $pair) {
            if (! is_string($field) || ! in_array($field, LiveEdits::REVIEW, true) || ! is_array($pair) || ! array_key_exists(1, $pair)) {
                continue;
            }
            $before[$field] = self::plain($unit->getAttribute($field));
            $copy->setAttribute($field, $pair[1]);
            $after[$field] = $pair[1];
        }
        if ($after === []) {
            throw ValidationException::withMessages(['change' => Text::get('changes.errors.empty')]);
        }
        StepRules::normalize($copy);
        $kept = self::plain($unit->next_due_date);
        $problems = [...$this->rules->problems(2, $copy), ...$this->rules->problems(3, $copy, is_string($kept) ? $kept : null)];
        if ($problems !== []) {
            throw ValidationException::withMessages(['change' => Text::get('changes.errors.invalid', ['problem' => (string) reset($problems)])]);
        }

        DB::transaction(function () use ($unit, $copy, $after, $change, $by): void {
            foreach (array_keys($after) as $field) {
                $unit->setAttribute($field, $copy->getAttribute($field));
            }
            $unit->save();
            $change->forceFill(['state' => 'approved', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'note' => null])->save();
        });
        $this->log->record('unit.change_approved', $unit, $before, $after);
    }

    /** Reject a pending figure change with a reason; the live unit keeps its values and the seller is told (G15). */
    public function reject(UnitPendingChange $change, AdminUser $by, string $reason): void
    {
        $unit = self::openUnit($change);
        $change->forceFill(['state' => 'rejected', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'note' => mb_substr($reason, 0, 500)])->save();
        $fields = [];
        foreach ($change->changes as $field => $pair) {
            if (is_string($field) && is_array($pair)) {
                $fields[] = ['field' => $field, 'note' => (FieldDisplay::value($field, $pair[0] ?? null) ?? '—').' → '.(FieldDisplay::value($field, $pair[1] ?? null) ?? '—')];
            }
        }
        $this->log->record('unit.change_rejected', $unit, null, ['fields' => array_keys($change->changes)], $reason);

        $seller = $unit->seller;
        if ($seller !== null) {
            $locale = $seller->preferredLocale();
            $this->outbox->send($seller->email, $seller->name, $locale, 'seller.change_rejected', [
                'kind' => 'change_rejected', 'reason' => $reason, 'fields' => $fields,
                'reference' => $unit->code ?? '',
                'account_url' => Links::frontend('/account/units/'.$unit->public_id, $locale),
            ]);
        }
    }

    /** The team looked at the free edits: the unit leaves the "check after edit" list. */
    public function checked(Unit $unit): void
    {
        if ($unit->recheck_after_edit_at === null) {
            return;
        }
        $unit->forceFill(['recheck_after_edit_at' => null])->save();
        $this->log->record('unit.recheck_done', $unit);
    }

    /**
     * Put back the description a seller's live edit replaced (G17), taken from that edit's log line. The text passes
     * the contact-details check again before it goes live.
     */
    public function restoreDescription(Unit $unit, int $logId, string $reason): void
    {
        if (! $unit->state->isPublic()) {
            throw ValidationException::withMessages(['unit' => Text::get('units.errors.state')]);
        }
        $line = ActivityLog::query()->whereKey($logId)->where('subject_type', 'units')->where('subject_id', $unit->id)->where('action', 'unit.live_edit')->first();
        $old = $line instanceof ActivityLog ? ($line->before ?? [])['description'] ?? null : null;
        if (! is_string($old) || $old === '') {
            throw ValidationException::withMessages(['description' => Text::get('changes.errors.no_description')]);
        }
        if (StepRules::hasContactDetails($old)) {
            throw ValidationException::withMessages(['description' => Text::get('changes.errors.contact_details')]);
        }
        $before = ['description' => $unit->description];
        $unit->forceFill(['description' => $old])->save();
        $this->log->record('unit.description_restored', $unit, $before, ['description' => $old], $reason);
    }

    /**
     * What the seller changed freely since the team last checked the unit, newest first.
     *
     * @return list<array{id: int, action: string, label: string, at: string, description_before: string|null, description_after: string|null, fields: list<string>}>
     */
    public static function freeEdits(Unit $unit): array
    {
        $since = ActivityLog::query()->where('subject_type', 'units')->where('subject_id', $unit->id)->where('action', 'unit.recheck_done')->latest('id')->first();
        $lines = ActivityLog::query()->where('subject_type', 'units')->where('subject_id', $unit->id)->whereIn('action', self::FREE_EDITS)
            ->when($since instanceof ActivityLog, fn ($q) => $q->where('id', '>', $since instanceof ActivityLog ? $since->id : 0))
            ->where('created_at', '>=', $unit->published_at ?? now()->subYears(5))
            ->latest('id')->limit(20)->get();
        $out = [];
        foreach ($lines as $line) {
            $before = $line->before ?? [];
            $after = $line->after ?? [];
            $out[] = [
                'id' => $line->id,
                'action' => $line->action,
                'label' => Text::get('units.events.'.str_replace('.', '_', $line->action)),
                'at' => $line->created_at->timezone('Africa/Cairo')->format('Y-m-d H:i'),
                'description_before' => is_string($before['description'] ?? null) ? $before['description'] : null,
                'description_after' => is_string($after['description'] ?? null) ? $after['description'] : null,
                'fields' => $line->action === 'unit.live_edit' ? array_map(static fn ($f): string => FieldDisplay::label((string) $f), array_keys(array_diff_key($after, ['description' => true]))) : [],
            ];
        }

        return $out;
    }

    private static function openUnit(UnitPendingChange $change): Unit
    {
        $unit = $change->unit;
        if ($change->state !== 'pending' || $unit === null || ! in_array($unit->state, [UnitState::Published, UnitState::Reserved], true)) {
            throw ValidationException::withMessages(['change' => Text::get('changes.errors.state')]);
        }

        return $unit;
    }

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
