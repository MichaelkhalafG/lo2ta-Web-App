<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's override of one duplicate block (Q23, D113): the unit may hold the same contract key as the matched unit.
 */
class DuplicateOverride extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function matchedUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'matched_unit_id');
    }
}
