<?php

namespace App\Support\Review;

use App\Enums\UnitState;
use App\Models\AdminUser;
use App\Models\DuplicateAttempt;
use App\Models\DuplicateOverride;
use App\Models\Unit;
use App\Support\Activity\ActivityLogger;
use App\Support\Dashboard\Text;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * The duplicate override (Michael's answer to Q23, 2026-11-28; PLAN 2.4; D113-D115). Admin only (the route's
 * `override-duplicates` ability, checked again here). An override on a blocked attempt lets that attempt's unit go on
 * although the matched unit holds the same contract key; it excuses that one pair, nothing more (DuplicateGuard).
 * A written reason is required; the activity log keeps who, when and what. The seller is told nothing.
 */
final class DuplicateOverrides
{
    /** Where a blocked unit can still be sent: before its first submit, or back with the seller. */
    private const OPEN_STATES = [UnitState::Draft, UnitState::ChangesRequested];

    public function __construct(private readonly ActivityLogger $log) {}

    /** Whether the attempt can be overridden at all (the screen shows the button only then). */
    public static function canOverride(DuplicateAttempt $attempt, ?Unit $unit, bool $overridden): bool
    {
        return $attempt->blocked
            && in_array($attempt->key_kind, ['contract', 'unit'], true)
            && $attempt->matched_unit_id !== null
            && $unit instanceof Unit && in_array($unit->state, self::OPEN_STATES, true)
            && ! $overridden;
    }

    public function override(DuplicateAttempt $attempt, AdminUser $by, string $reason): DuplicateOverride
    {
        if (! $by->is_active || ! $by->isAdmin()) {
            throw ValidationException::withMessages(['attempt' => Text::get('duplicates.errors.admin_only')]);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => Text::get('units.errors.reason_required')]);
        }
        $unit = $attempt->unit_id !== null ? Unit::query()->find($attempt->unit_id) : null;
        $matched = $attempt->matched_unit_id !== null ? Unit::query()->find($attempt->matched_unit_id, ['id']) : null;
        $overridden = $unit instanceof Unit && DuplicateOverride::query()
            ->where('unit_id', $unit->id)->where('matched_unit_id', $attempt->matched_unit_id)->exists();
        if ($overridden) {
            throw ValidationException::withMessages(['attempt' => Text::get('duplicates.errors.already')]);
        }
        if (! $unit instanceof Unit || ! $matched instanceof Unit || ! self::canOverride($attempt, $unit, false)) {
            throw ValidationException::withMessages(['attempt' => Text::get('duplicates.errors.not_overridable')]);
        }

        try {
            $override = DuplicateOverride::query()->create([
                'attempt_id' => $attempt->id,
                'unit_id' => $unit->id,
                'matched_unit_id' => $matched->id,
                'reason' => $reason,
                'created_by' => $by->id,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Two admins at the same moment: the unique pair decides.
            if (($e->errorInfo[0] ?? null) === '23000') {
                throw ValidationException::withMessages(['attempt' => Text::get('duplicates.errors.already')]);
            }

            throw $e;
        }

        $this->log->record('duplicate.overridden', $unit, ['blocked_by_unit' => $matched->id], [
            'attempt' => $attempt->id,
            'excused_unit' => $matched->id,
            'key' => $attempt->key_kind,
        ], $reason);

        return $override;
    }
}
