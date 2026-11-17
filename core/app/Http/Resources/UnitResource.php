<?php

namespace App\Http\Resources;

use App\Enums\InstallmentFrequency;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\Feature;
use App\Models\Unit;
use App\Models\UnitPhoto;
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
            'area' => $area instanceof Area
                ? ['slug' => $area->slug, 'name' => $area->nameFor($locale)] : null,
            'contract_year' => $unit->contract_year,
            'size_m2' => $unit->size_m2,
            'garden_m2' => $unit->garden_m2,
            'roof_m2' => $unit->roof_m2,
            'floor' => $unit->floor,
            'bedrooms' => $unit->bedrooms,
            'bathrooms' => $unit->bathrooms,
            'finishing' => self::choice($unit->finishing),
            'delivery_status' => self::choice($unit->delivery_status),
            'delivery_year' => $unit->delivery_year,
            'facade' => self::facade($unit->facade),
            'description' => $unit->description,
            'features' => $unit->features->map(static fn (Feature $f): array => [
                'code' => $f->code,
                'group' => $f->group->value,
                'name' => $f->nameFor($locale),
            ])->values()->all(),
            'photos' => $unit->photos->map(static fn (UnitPhoto $p): array => [
                'kind' => $p->kind->value,
                'src' => Storage::disk('public')->url($p->path),
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
                'maintenance_due' => $unit->maintenance_due,
            ],
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
     * @return list<string>|null
     */
    private static function facade(?int $bits): ?array
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
