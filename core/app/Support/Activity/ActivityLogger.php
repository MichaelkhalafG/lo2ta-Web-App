<?php

namespace App\Support\Activity;

use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records every write action (handover 2.3). Dashboard-authored models log themselves through LogsActivity;
 * domain actions (publish, verify, withdraw ...) call record() with their own action name and reason.
 *
 * The actor is resolved from the admin guard first, then the public guard, else "system" (console, scheduler).
 */
final class ActivityLogger
{
    private ?string $pendingReason = null;

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): ActivityLog {
        [$actorType, $actorId] = $this->actor();

        $key = $subject?->getKey();

        return ActivityLog::query()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject?->getTable(),
            'subject_id' => is_int($key) ? $key : null,
            'before' => $before,
            'after' => $after,
            'reason' => $reason ?? $this->pendingReason,
            'ip' => app()->runningInConsole() ? null : Request::ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Run $callback with a reason attached to every automatic log line it causes.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withReason(string $reason, callable $callback): mixed
    {
        $previous = $this->pendingReason;
        $this->pendingReason = $reason;

        try {
            return $callback();
        } finally {
            $this->pendingReason = $previous;
        }
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function actor(): array
    {
        $admin = Auth::guard('admin')->user();
        if ($admin instanceof AdminUser) {
            return ['admin', $admin->id];
        }

        // Public users act through the API with a Sanctum token (RUN D10); the web guard is kept for completeness.
        foreach (['sanctum', 'web'] as $guard) {
            $user = Auth::guard($guard)->user();
            if ($user instanceof User) {
                return ['user', $user->id];
            }
        }

        return ['system', null];
    }
}
