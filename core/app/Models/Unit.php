<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\Finishing;
use App\Enums\InstallmentFrequency;
use App\Enums\MainType;
use App\Enums\SubType;
use App\Enums\UnitState;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A seller's submission and its public listing (PLAN 3.1 option A).
 *
 * $fillable lists only what the seller types. State, codes, duplicate keys, flags and dates are set by the domain
 * services with forceFill, so a request can never set them. Private columns are listed in PRIVATE and must never
 * reach a public resource (tests/Feature/Api/PrivateFieldsTest.php).
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /** Columns that must never appear in any public API answer. */
    public const PRIVATE = [
        'seller_id', 'contract_number', 'unit_number', 'contract_key', 'unit_key', 'active_contract_key',
        'active_unit_key', 'developer_other', 'compound_other', 'area_other', 'verified_by', 'withdraw_reason',
        'step_reached', 'rejected_at', 'withdrawn_at', 'submitted_at',
    ];

    protected $fillable = [
        // Step 1
        'developer_id', 'developer_other', 'compound_id', 'compound_other', 'area_id', 'area_other',
        'main_type', 'sub_type', 'contract_number', 'unit_number', 'contract_year',
        // Step 2
        'size_m2', 'garden_m2', 'roof_m2', 'floor', 'bedrooms', 'bathrooms', 'finishing', 'delivery_status',
        'delivery_year', 'facade', 'description',
        // Step 3
        'total_price', 'paid_amount', 'market_price_estimate', 'installment_amount', 'installment_frequency',
        'next_due_date', 'years_left', 'annual_extra_amount', 'delivery_payment', 'accepts_less',
        'lowest_accepted_amount', 'has_overdue', 'overdue_count', 'overdue_amount', 'maintenance_due',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => UnitState::class,
            'main_type' => MainType::class,
            'sub_type' => SubType::class,
            'finishing' => Finishing::class,
            'delivery_status' => DeliveryStatus::class,
            'installment_frequency' => InstallmentFrequency::class,
            'size_m2' => 'decimal:2',
            'garden_m2' => 'decimal:2',
            'roof_m2' => 'decimal:2',
            'total_price' => 'integer',
            'paid_amount' => 'integer',
            'remaining_amount' => 'integer',
            'market_price_estimate' => 'integer',
            'installment_amount' => 'integer',
            'annual_extra_amount' => 'integer',
            'delivery_payment' => 'integer',
            'lowest_accepted_amount' => 'integer',
            'overdue_amount' => 'integer',
            'maintenance_due' => 'integer',
            'next_due_date' => 'immutable_date',
            'accepts_less' => 'boolean',
            'has_overdue' => 'boolean',
            'deal_tag' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return BelongsTo<Developer, $this>
     */
    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    /**
     * @return BelongsTo<Compound, $this>
     */
    public function compound(): BelongsTo
    {
        return $this->belongsTo(Compound::class);
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'unit_feature');
    }

    /**
     * @return HasMany<UnitPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(UnitPhoto::class)->orderBy('sort');
    }

    /**
     * @return HasMany<UnitDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(UnitDocument::class);
    }

    /**
     * @return HasMany<UnitDeclaration, $this>
     */
    public function declarations(): HasMany
    {
        return $this->hasMany(UnitDeclaration::class);
    }

    /**
     * @return HasMany<UnitReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(UnitReview::class);
    }

    /**
     * @return HasMany<UnitPendingChange, $this>
     */
    public function pendingChanges(): HasMany
    {
        return $this->hasMany(UnitPendingChange::class);
    }

    /**
     * @return HasMany<ExitReport, $this>
     */
    public function exitReports(): HasMany
    {
        return $this->hasMany(ExitReport::class);
    }

    /**
     * Units a visitor may open (published or reserved).
     *
     * @param  Builder<Unit>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->whereIn('state', UnitState::publicValues());
    }

    /**
     * Default sort (decided 2026-11-17): units whose seller accepts less first, then newest.
     *
     * @param  Builder<Unit>  $query
     */
    public function scopeDefaultOrder(Builder $query): void
    {
        $query->orderByDesc('accepts_less')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
