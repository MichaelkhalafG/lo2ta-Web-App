<?php

namespace App\Support\People;

use App\Enums\UnitState;
use App\Models\AdminUser;
use App\Models\Unit;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Uploads\PhotoPublisher;
use Illuminate\Support\Facades\DB;

/**
 * Suspending a seller or buyer (G4, Michael 2026-11-27): admin only, with a reason, logged and reversible. It blocks
 * sign-in (every token is revoked and the login refuses the account) and holds the person's units off the site: their
 * state is kept and `held_at` is set, the public photo copies are removed. Lifting it puts both back as they were.
 */
final class Suspension
{
    public function __construct(private readonly ActivityLogger $log, private readonly PhotoPublisher $photos) {}

    public function suspend(User $user, AdminUser $by, string $reason): void
    {
        if ($user->isSuspended()) {
            return;
        }
        $held = DB::transaction(function () use ($user, $by, $reason): array {
            $user->forceFill(['suspended_at' => now(), 'suspended_reason' => mb_substr($reason, 0, 500), 'suspended_by' => $by->id])->save();
            $user->tokens()->delete();
            $units = Unit::query()->where('seller_id', $user->id)->whereIn('state', UnitState::publicValues())->whereNull('held_at')->get();
            foreach ($units as $unit) {
                $unit->forceFill(['held_at' => now()])->save();
            }

            return $units->all();
        });
        foreach ($held as $unit) {
            foreach ($unit->photos()->get() as $photo) {
                $this->photos->withdraw($photo);
            }
            $this->log->record('unit.held', $unit, ['on_site' => true], ['on_site' => false], $reason);
        }
        $this->log->record('user.suspended', $user, ['suspended' => false], ['suspended' => true, 'units_held' => array_map(static fn (Unit $u): int => $u->id, $held)], $reason);
    }

    public function lift(User $user, string $reason): void
    {
        if (! $user->isSuspended()) {
            return;
        }
        $released = DB::transaction(function () use ($user): array {
            $user->forceFill(['suspended_at' => null, 'suspended_reason' => null, 'suspended_by' => null])->save();
            $units = Unit::query()->where('seller_id', $user->id)->whereNotNull('held_at')->get();
            foreach ($units as $unit) {
                $unit->forceFill(['held_at' => null])->save();
            }

            return $units->all();
        });
        foreach ($released as $unit) {
            if ($unit->state->isPublic()) {
                foreach ($unit->photos()->get() as $photo) {
                    $this->photos->publish($photo);
                }
            }
            $this->log->record('unit.released', $unit, ['on_site' => false], ['on_site' => $unit->state->isPublic()], $reason);
        }
        $this->log->record('user.unsuspended', $user, ['suspended' => true], ['suspended' => false], $reason);
    }
}
