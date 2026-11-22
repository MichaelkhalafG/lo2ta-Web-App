<?php

namespace App\Http\Resources;

use App\Enums\InstallmentFrequency;
use App\Enums\OverdueSettlement;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\Feature;
use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Support\Listing\UnitFacts;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The ONLY shape in which a unit leaves core for the public site. Every field is listed by hand; nothing is
 * spread from the model. Private columns (Unit::PRIVATE), documents, the seller and who verified are never
 * here. tests/Feature/Api/PrivateFieldsTest.php fails if a key is added without updating its approved list.
 *
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /** Relations the resource reads; every query that feeds it eager-loads exactly these. */
    public const WITH = ['developer', 'compound', 'area.parent.parent', 'features', 'visiblePhotos'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Unit $unit */
        $unit = $this->resource;
        $locale = app()->getLocale();
        $developer = $unit->developer;
        $compound = $unit->compound;
        $area = $unit->area;

        return [
            'code' => $unit->code,
            'slug' => $unit->slug,
            'title' => UnitFacts::title($unit, $locale),
            'state' => $unit->state->value,
            'is_verified' => $unit->isVerified(),
            'deal_tag' => $unit->deal_tag,
            'published_at' => $unit->published_at?->toIso8601String(),
            'main_type' => self::choice($unit->main_type),
            'sub_type' => self::choice($unit->sub_type),
            'developer' => $developer instanceof Developer
                ? ['slug' => $developer->slug, 'name' => $developer->nameFor($locale)] : null,
            'compound' => $compound instanceof Compound
                ? ['slug' => $compound->slug, 'name' => $compound->nameFor($locale)] : null,
            // Governorate > area > district (PLAN 3.3, 2.5): names only, never an address or a map position.
            'location' => self::location($area, $locale),
            'contract_year' => $unit->contract_year,
            'contract_month' => $unit->contract_month,
            'size_m2' => $unit->size_m2,
            'garden_m2' => $unit->garden_m2,
            'roof_m2' => $unit->roof_m2,
            'floor' => $unit->floor,
            'bedrooms' => $unit->bedrooms,
            'bathrooms' => $unit->bathrooms,
            'finishing' => self::choice($unit->finishing),
            'delivery_status' => self::choice($unit->delivery_status),
            'delivery_year' => $unit->delivery_year,
            'delivery_month' => $unit->delivery_month,
            'facade' => self::facadeOf($unit->facade),
            'description' => $unit->description,
            'features' => $unit->features->map(static fn (Feature $f): array => [
                'code' => $f->code,
                'group' => $f->group->value,
                'name' => $f->nameFor($locale),
            ])->values()->all(),
            // Only photos the admin has not hidden (PLAN 2.5).
            'photos' => $unit->visiblePhotos->map(static fn (UnitPhoto $p): array => [
                'kind' => $p->kind->value,
                // The public copy only; the private path is never sent (visiblePhotos guarantees a public copy).
                'src' => Storage::disk('public')->url((string) $p->public_path),
                'width' => $p->width,
                'height' => $p->height,
                'is_project_image' => $p->is_project_image,
            ])->values()->all(),
            'money' => [
                'total_price' => $unit->total_price,
                'paid_amount' => $unit->paid_amount,
                'remaining_amount' => $unit->remaining_amount,
                'market_price_estimate' => $unit->market_price_estimate,
                'installment_amount' => $unit->installment_amount,
                'installment_frequency' => self::choice($unit->installment_frequency),
                'installments_per_year' => $unit->installment_frequency instanceof InstallmentFrequency
                    ? $unit->installment_frequency->perYear() : null,
                'next_due_date' => $unit->next_due_date?->toDateString(),
                'years_left' => $unit->years_left,
                'annual_extra_amount' => $unit->annual_extra_amount,
                'delivery_payment' => $unit->delivery_payment,
                'accepts_less' => $unit->accepts_less,
                'lowest_accepted_amount' => $unit->accepts_less ? $unit->lowest_accepted_amount : null,
                'has_overdue' => $unit->has_overdue,
                'overdue_count' => $unit->has_overdue ? $unit->overdue_count : null,
                'overdue_amount' => $unit->has_overdue ? $unit->overdue_amount : null,
                'overdue_settlement' => $unit->has_overdue ? self::choice($unit->overdue_settlement) : null,
                // "Of which X goes to the developer to settle arrears" (PLAN 2.2.12): only when deducted.
                'to_developer_for_arrears' => $unit->has_overdue && $unit->overdue_settlement === OverdueSettlement::Deducted
                    ? $unit->overdue_amount : null,
                'maintenance_due' => $unit->maintenance_due,
                // What a buyer reads off the money panel (board 06.3, PLAN 2.5).
                'owner_from' => UnitFacts::ownerFrom($unit),
                'paid_percent' => UnitFacts::paidPercent($unit),
                'installments_left' => UnitFacts::installmentsLeft($unit),
                'buyer_fee' => UnitFacts::buyerFee($unit, app(Settings::class)),
            ],
            // Every installment date until the last (board 06.3), computed from the public money fields.
            'schedule' => UnitFacts::schedule($unit),
        ];
    }

    /**
     * @return array{code: string, label: string}|null
     */
    private static function choice(?\BackedEnum $value): ?array
    {
        if ($value === null || ! method_exists($value, 'label')) {
            return null;
        }
        $label = $value->label();

        return ['code' => (string) $value->value, 'label' => is_string($label) ? $label : (string) $value->value];
    }

    /**
     * The unit's place as governorate, area and district, from the most specific level the unit points at.
     *
     * @return array{governorate: array{slug: string, name: string}|null, area: array{slug: string, name: string}|null, district: array{slug: string, name: string}|null}|null
     */
    private static function location(?Area $place, string $locale): ?array
    {
        if (! $place instanceof Area) {
            return null;
        }

        $chain = [];
        for ($node = $place; $node instanceof Area && count($chain) < 3; $node = $node->parent) {
            array_unshift($chain, ['slug' => $node->slug, 'name' => $node->nameFor($locale)]);
        }

        return [
            'governorate' => $chain[0],
            'area' => $chain[1] ?? null,
            'district' => $chain[2] ?? null,
        ];
    }

    /**
     * @return list<string>|null
     */
    public static function facadeOf(?int $bits): ?array
    {
        if ($bits === null || $bits === 0) {
            return null;
        }

        $sides = [];
        foreach ([1 => 'north', 2 => 'east', 4 => 'south', 8 => 'west'] as $bit => $side) {
            if (($bits & $bit) !== 0) {
                $sides[] = $side;
            }
        }

        return $sides;
    }
}
