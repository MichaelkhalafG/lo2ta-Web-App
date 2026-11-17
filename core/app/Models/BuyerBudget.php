<?php

namespace App\Models;

use App\Enums\DeliveryPreference;
use App\Enums\InstallmentFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * What a buyer can pay and what they look for (PLAN 2.3). The admin matches it against units in phase 7.
 */
class BuyerBudget extends Model
{
    protected $fillable = [
        'cash_available', 'max_installment', 'installment_frequency', 'main_types', 'target_price', 'min_bedrooms',
        'delivery_pref',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cash_available' => 'integer',
            'max_installment' => 'integer',
            'installment_frequency' => InstallmentFrequency::class,
            'main_types' => 'array',
            'target_price' => 'integer',
            'min_bedrooms' => 'integer',
            'delivery_pref' => DeliveryPreference::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Area, $this>
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'buyer_budget_areas');
    }

    /**
     * @return BelongsToMany<Compound, $this>
     */
    public function compounds(): BelongsToMany
    {
        return $this->belongsToMany(Compound::class, 'buyer_budget_compounds');
    }
}
