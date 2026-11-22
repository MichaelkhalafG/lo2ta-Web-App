<?php

namespace App\Models;

use App\Enums\Readiness;
use App\Enums\RequestState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A buyer asks for a unit (PLAN 2.3), with the 1% fee consent as the buyer saw it. Private: only the buyer (their own
 * list) and staff (phase 7 inbox) ever see it.
 */
class UnitRequest extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(static function (UnitRequest $request): void {
            $request->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'readiness' => Readiness::class,
            'state' => RequestState::class,
            'fee_consent_amount' => 'integer',
            'fee_consent_bp' => 'integer',
            'fee_consent_at' => 'immutable_datetime',
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
}
