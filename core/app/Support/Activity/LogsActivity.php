<?php

namespace App\Support\Activity;

use Illuminate\Database\Eloquent\Model;

/**
 * Logs created / updated / deleted for a dashboard-authored model, with the changed values before and after.
 * Hidden attributes (passwords, tokens) are never written to the log.
 *
 * @mixin Model
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(static function (Model $model): void {
            app(ActivityLogger::class)->record(
                $model->getTable().'.created',
                $model,
                null,
                self::loggable($model, $model->getAttributes()),
            );
        });

        static::updated(static function (Model $model): void {
            $changes = self::loggable($model, $model->getChanges());
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }

            $before = [];
            foreach (array_keys($changes) as $key) {
                $before[$key] = $model->getOriginal($key);
            }

            app(ActivityLogger::class)->record(
                $model->getTable().'.updated',
                $model,
                self::loggable($model, $before),
                $changes,
            );
        });

        static::deleted(static function (Model $model): void {
            app(ActivityLogger::class)->record(
                $model->getTable().'.deleted',
                $model,
                self::loggable($model, $model->getAttributes()),
                null,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function loggable(Model $model, array $values): array
    {
        $hidden = array_merge($model->getHidden(), ['password', 'remember_token']);

        $out = [];
        foreach ($values as $key => $value) {
            if (in_array($key, $hidden, true)) {
                continue;
            }
            $out[$key] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return $out;
    }
}
