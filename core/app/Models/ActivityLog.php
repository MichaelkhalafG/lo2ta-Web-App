<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only: rows are written by App\Support\Activity\ActivityLogger and never edited.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
