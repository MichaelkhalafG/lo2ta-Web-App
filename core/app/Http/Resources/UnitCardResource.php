<?php

namespace App\Http\Resources;

use App\Models\Area;
use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Support\Listing\UnitFacts;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The unit sheet card (board "Unit sheet card", PLAN 2.5 card row): exactly the card fields, hand-listed like
 * UnitResource. Nothing private can appear: tests/Feature/Api/PrivateFieldsTest.php pins the keys and scans the answer.
 */
class UnitCardResource extends JsonResource
{
    /** Relations the card reads; every query that feeds it eager-loads exactly these. */
    public const WITH = ['area.parent', 'coverPhoto'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Unit $unit */
        $unit = $this->resource;
        $locale = app()->getLocale();
        $area = $unit->area;
        $cover = $unit->coverPhoto;

        return [
            'code' => $unit->code,
            'slug' => $unit->slug,
            'title' => UnitFacts::title($unit, $locale),
            'main_type' => self::choice($unit->main_type, $locale),
            'sub_type' => self::choice($unit->sub_type, $locale),
            'size_m2' => $unit->size_m2,
            'facade' => UnitResource::facadeOf($unit->facade),
            // District and area by name (PLAN 2.5): the most specific level first.
            'place' => $area instanceof Area ? array_values(array_filter([
                $area->nameFor($locale),
                $area->parent instanceof Area && $area->parent->parent_id !== null ? $area->parent->nameFor($locale) : null,
            ])) : [],
            'delivery' => ['status' => $unit->delivery_status?->value, 'month' => $unit->delivery_month, 'year' => $unit->delivery_year],
            'total_price' => $unit->total_price,
            'market_price_estimate' => $unit->market_price_estimate,
            'paid_amount' => $unit->paid_amount,
            'remaining_amount' => $unit->remaining_amount,
            'paid_percent' => UnitFacts::paidPercent($unit),
            'owner_from' => UnitFacts::ownerFrom($unit),
            'accepts_less' => $unit->accepts_less,
            'installments' => [
                'count' => UnitFacts::installmentsLeft($unit),
                'amount' => $unit->installment_amount,
                'frequency' => self::choice($unit->installment_frequency, $locale),
            ],
            'is_verified' => $unit->isVerified(),
            'deal_tag' => $unit->deal_tag,
            'is_reserved' => $unit->state->value === 'reserved',
            'cover' => $cover instanceof UnitPhoto && is_string($cover->public_path) ? [
                'src' => Storage::disk('public')->url($cover->public_path),
                'width' => $cover->width,
                'height' => $cover->height,
                'is_project_image' => $cover->is_project_image,
            ] : null,
        ];
    }

    /**
     * @return array{code: string, label: string}|null
     */
    private static function choice(?BackedEnum $value, string $locale): ?array
    {
        if ($value === null || ! method_exists($value, 'label')) {
            return null;
        }
        $label = $value->label($locale);

        return ['code' => (string) $value->value, 'label' => is_string($label) ? $label : (string) $value->value];
    }
}
