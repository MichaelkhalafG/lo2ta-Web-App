<?php

namespace App\Models;

use App\Enums\DealStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A deal between a buyer and a unit (PLAN 2.4, Q16; part 7.5). Every field is set by DealFlow with forceFill, so a
 * request can never set one; every change writes a named log line (deal.*).
 */
class Deal extends Model
{
    protected $guarded = ['id'];

    public function reference(): string
    {
        return sprintf('D-%04d', $this->id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'opened_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return BelongsTo<UnitRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(UnitRequest::class, 'unit_request_id');
    }

    /**
     * @return HasMany<DealStageEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(DealStageEvent::class);
    }

    /**
     * @return HasOne<Commission, $this>
     */
    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }
}
