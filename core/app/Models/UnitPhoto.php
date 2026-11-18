<?php

namespace App\Models;

use App\Enums\PhotoKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A listing photo or floor plan. `path` is the re-encoded WebP on the PRIVATE disk; `public_path` is its copy on the
 * public disk, present only while the unit is published and the photo is not hidden (PLAN 2.2.15).
 */
class UnitPhoto extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(static function (UnitPhoto $photo): void {
            $photo->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PhotoKind::class,
            'is_project_image' => 'boolean',
            'is_hidden' => 'boolean',
            'hidden_at' => 'immutable_datetime',
            'sort' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
