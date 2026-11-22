<?php

namespace App\Support\Seller;

use App\Enums\DeliveryStatus;
use App\Enums\DocumentType;
use App\Enums\Finishing;
use App\Enums\InstallmentFrequency;
use App\Enums\MainType;
use App\Enums\OverdueSettlement;
use App\Enums\PhotoKind;
use App\Enums\SubType;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\Feature;
use App\Models\Unit;
use App\Support\Settings\Settings;
use App\Support\Text\ContactDetails;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The six steps' rules (PLAN 2.2.2, 2.2.3, 2.2.9, 2.2.12, 2.2.13). Two layers:
 * - apply(): what an autosave may store. Every field is optional, but each one present must have the right type and
 *   limits, so the database never holds garbage; fields that do not apply to the sub-type are cleared.
 * - problems(): what a complete step needs, with the cross-field checks. "Next" and submit both use it, on the stored
 *   unit, so a request can never skip a check by sending a different body.
 */
final class StepRules
{
    public const MONEY_MAX = 1_000_000_000;

    public const INSTALLMENT_MAX = 100_000_000;

    public const FLOORS = ['basement', 'ground', 'mezzanine'];

    private const FACADE_BITS = ['north' => 1, 'east' => 2, 'south' => 4, 'west' => 8];

    public function __construct(private readonly Settings $settings) {}

    /**
     * Validate $input for $step and fill the unit (not saved). Step 2 returns the feature ids to sync.
     *
     * @param  array<string, mixed>  $input
     * @return list<int>|null feature ids for step 2, null when features were not sent
     */
    public function apply(int $step, Unit $unit, array $input): ?array
    {
        $rules = match ($step) {
            1 => self::step1Rules(),
            2 => self::step2Rules(),
            3 => self::step3Rules(),
            5 => ['description' => ['nullable', 'string', 'max:'.$this->settings->int('description_max')]],
            default => throw ValidationException::withMessages(['step' => self::text('seller.rules.step')]),
        };
        $data = [];
        foreach (Validator::make($input, $rules)->validate() as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }

        $features = null;
        if ($step === 1) {
            self::pair($unit, $data, 'developer');
            self::pair($unit, $data, 'compound');
            self::pair($unit, $data, 'area');
            unset($data['developer'], $data['developer_other'], $data['compound'], $data['compound_other'], $data['area'], $data['area_other']);
            foreach (['contract_number', 'unit_number'] as $key) {
                if (array_key_exists($key, $data) && is_string($data[$key])) {
                    $data[$key] = trim($data[$key]);
                }
            }
        }
        if ($step === 2) {
            if (array_key_exists('facade', $data)) {
                $data['facade'] = self::facadeBits(is_array($data['facade']) ? $data['facade'] : null);
            }
            if (array_key_exists('features', $data)) {
                $features = self::featureIds(is_array($data['features']) ? $data['features'] : []);
                unset($data['features']);
            }
        }
        if ($step === 5 && isset($data['description']) && is_string($data['description'])) {
            $data['description'] = trim($data['description']);
        }

        $unit->fill($data);
        self::normalize($unit);

        return $features;
    }

    /**
     * Clear what does not apply (PLAN 2.2.3 matrix, 2.2.9, 2.2.12), so hidden fields never keep stale values.
     */
    public static function normalize(Unit $unit): void
    {
        $sub = $unit->sub_type;
        if ($sub instanceof SubType) {
            if (! $sub->hasFloor()) {
                $unit->floor = $sub === SubType::GardenApartment ? 'ground' : null;
            }
            if (! $sub->hasRooms()) {
                $unit->bedrooms = null;
                $unit->bathrooms = null;
            } elseif ($sub->fixedBedrooms() !== null) {
                $unit->bedrooms = $sub->fixedBedrooms();
            }
            if (! $sub->hasGarden()) {
                $unit->garden_m2 = null;
            }
            if (! $sub->hasRoof()) {
                $unit->roof_m2 = null;
            }
            if (! $sub->hasFinishing()) {
                $unit->finishing = null;
            }
        }
        if (! $unit->accepts_less) {
            $unit->lowest_accepted_amount = null;
        }
        if (! $unit->has_overdue) {
            $unit->overdue_count = null;
            $unit->overdue_amount = null;
            $unit->overdue_settlement = null;
        }
        if ($unit->total_price !== null && $unit->paid_amount !== null && $unit->paid_amount === $unit->total_price) {
            // Fully paid: "installments finished" (2.2.9 point 1).
            $unit->installment_amount = null;
            $unit->installment_frequency = null;
            $unit->next_due_date = null;
            $unit->years_left = 0;
            $unit->annual_extra_amount = null;
        }
    }

    /**
     * What a complete step still needs, field => message; [] when the step is complete.
     *
     * @return array<string, string>
     */
    public function problems(int $step, Unit $unit): array
    {
        return match ($step) {
            1 => self::step1Problems($unit),
            2 => self::step2Problems($unit),
            3 => self::step3Problems($unit),
            4 => $this->step4Problems($unit),
            5 => $this->step5Problems($unit),
            default => [],
        };
    }

    /**
     * Non-blocking notes for step 3 (PLAN 2.2.2): the installments do not roughly add up to the remaining amount.
     *
     * @return list<string>
     */
    public function warnings(Unit $unit): array
    {
        $remaining = ($unit->total_price ?? 0) - ($unit->paid_amount ?? 0);
        $frequency = $unit->installment_frequency;
        if ($remaining <= 0 || $unit->installment_amount === null || ! $frequency instanceof InstallmentFrequency || $unit->years_left === null) {
            return [];
        }
        $expected = $unit->installment_amount * $frequency->perYear() * $unit->years_left
            + ($unit->annual_extra_amount ?? 0) * $unit->years_left
            + ($unit->delivery_payment ?? 0);
        $tolerance = $this->settings->int('installment_check_tolerance_bp') / 10_000;

        return abs($expected - $remaining) > $remaining * $tolerance ? ['installments_mismatch'] : [];
    }

    /**
     * Phone numbers, e-mails and links are refused in the description (PLAN 2.2.9 point 6).
     */
    public static function hasContactDetails(string $text): bool
    {
        return ContactDetails::has($text);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function step1Rules(): array
    {
        return [
            // Places are named by their public slug, as in the public lists; the database ids never leave core.
            'developer' => ['nullable', 'string', 'max:96', Rule::exists('developers', 'slug')->where('is_active', true)],
            'developer_other' => ['nullable', 'string', 'min:2', 'max:120'],
            'compound' => ['nullable', 'string', 'max:96', Rule::exists('compounds', 'slug')->where('is_active', true)],
            'compound_other' => ['nullable', 'string', 'min:2', 'max:120'],
            // An area or a district; a governorate alone is too broad for the unit code (PLAN 3.3).
            'area' => ['nullable', 'string', 'max:96', Rule::exists('areas', 'slug')->where('is_active', true)->whereNotNull('parent_id')],
            'area_other' => ['nullable', 'string', 'min:2', 'max:120'],
            'main_type' => ['nullable', Rule::enum(MainType::class)],
            'sub_type' => ['nullable', Rule::enum(SubType::class)],
            'contract_number' => ['nullable', 'string', 'max:64'],
            'unit_number' => ['nullable', 'string', 'max:64'],
            'contract_year' => ['nullable', 'integer', 'between:1990,'.CarbonImmutable::now()->year],
            'contract_month' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function step2Rules(): array
    {
        $floors = [...self::FLOORS, ...array_map('strval', range(1, 40))];

        return [
            'size_m2' => ['nullable', 'numeric', 'between:1,100000', 'decimal:0,2'],
            'garden_m2' => ['nullable', 'numeric', 'between:0,100000', 'decimal:0,2'],
            'roof_m2' => ['nullable', 'numeric', 'between:0,100000', 'decimal:0,2'],
            'floor' => ['nullable', 'string', Rule::in($floors)],
            'bedrooms' => ['nullable', 'integer', 'between:0,20'],
            'bathrooms' => ['nullable', 'integer', 'between:0,20'],
            'finishing' => ['nullable', Rule::enum(Finishing::class)],
            'delivery_status' => ['nullable', Rule::enum(DeliveryStatus::class)],
            'delivery_year' => ['nullable', 'integer', 'between:1990,'.(CarbonImmutable::now()->year + 20)],
            'delivery_month' => ['nullable', 'integer', 'between:1,12'],
            'facade' => ['nullable', 'array', 'max:4'],
            'facade.*' => ['string', 'distinct', Rule::in(array_keys(self::FACADE_BITS))],
            'features' => ['nullable', 'array', 'max:40'],
            'features.*' => ['string', 'distinct', 'max:40'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function step3Rules(): array
    {
        $money = ['nullable', 'integer', 'between:1,'.self::MONEY_MAX];

        return [
            'total_price' => $money,
            'paid_amount' => $money,
            'market_price_estimate' => $money,
            'installment_amount' => ['nullable', 'integer', 'between:1,'.self::INSTALLMENT_MAX],
            'installment_frequency' => ['nullable', Rule::enum(InstallmentFrequency::class)],
            'next_due_date' => ['nullable', 'date_format:Y-m-d'],
            'years_left' => ['nullable', 'integer', 'between:0,40'],
            'annual_extra_amount' => $money,
            'delivery_payment' => $money,
            'accepts_less' => ['sometimes', 'boolean'],
            'lowest_accepted_amount' => $money,
            'has_overdue' => ['sometimes', 'boolean'],
            'overdue_count' => ['nullable', 'integer', 'between:1,500'],
            'overdue_amount' => $money,
            'overdue_settlement' => ['nullable', Rule::enum(OverdueSettlement::class)],
            'maintenance_due' => ['nullable', 'integer', 'between:0,'.self::MONEY_MAX],
            'transfer_fee_bp' => ['nullable', 'integer', 'between:0,2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function step1Problems(Unit $unit): array
    {
        $p = [];
        if ($unit->developer_id === null && blank($unit->developer_other)) {
            $p['developer'] = self::text('seller.rules.required');
        }
        if ($unit->compound_id === null && blank($unit->compound_other)) {
            $p['compound'] = self::text('seller.rules.required');
        } elseif ($unit->compound_id !== null && $unit->developer_id !== null
            && Compound::query()->whereKey($unit->compound_id)->value('developer_id') !== $unit->developer_id) {
            $p['compound'] = self::text('seller.rules.compound_developer');
        }
        if ($unit->area_id === null && blank($unit->area_other)) {
            $p['area'] = self::text('seller.rules.required');
        }
        if (! $unit->main_type instanceof MainType) {
            $p['main_type'] = self::text('seller.rules.required');
        }
        if (! $unit->sub_type instanceof SubType) {
            $p['sub_type'] = self::text('seller.rules.required');
        } elseif ($unit->main_type instanceof MainType && $unit->sub_type->mainType() !== $unit->main_type) {
            $p['sub_type'] = self::text('seller.rules.sub_type_main');
        }
        foreach (['contract_number', 'unit_number', 'contract_year', 'contract_month'] as $key) {
            if (blank($unit->{$key})) {
                $p[$key] = self::text('seller.rules.required');
            }
        }
        if ($unit->contract_year !== null && $unit->contract_month !== null
            && sprintf('%04d-%02d', $unit->contract_year, $unit->contract_month) > CarbonImmutable::now()->format('Y-m')) {
            $p['contract_month'] = self::text('seller.rules.date_future');
        }

        return $p;
    }

    /**
     * @return array<string, string>
     */
    private static function step2Problems(Unit $unit): array
    {
        $sub = $unit->sub_type;
        if (! $sub instanceof SubType) {
            return ['sub_type' => self::text('seller.rules.step1_first')];
        }
        $p = [];
        if ($unit->size_m2 === null) {
            $p['size_m2'] = self::text('seller.rules.required');
        }
        if ($sub->hasFloor() && blank($unit->floor)) {
            $p['floor'] = self::text('seller.rules.required');
        }
        if ($sub->hasRooms()) {
            if ($unit->bedrooms === null) {
                $p['bedrooms'] = self::text('seller.rules.required');
            }
            if ($unit->bathrooms === null || $unit->bathrooms < 1) {
                $p['bathrooms'] = self::text('seller.rules.required');
            }
        }
        if ($sub->hasFinishing() && ! $unit->finishing instanceof Finishing) {
            $p['finishing'] = self::text('seller.rules.required');
        }
        if (! $unit->delivery_status instanceof DeliveryStatus) {
            $p['delivery_status'] = self::text('seller.rules.required');
        } elseif ($unit->delivery_status === DeliveryStatus::UnderConstruction) {
            if ($unit->delivery_year === null || $unit->delivery_month === null) {
                $p['delivery_year'] = self::text('seller.rules.required');
            }
        }
        if ($unit->delivery_year !== null && $unit->contract_year !== null && $unit->delivery_year < $unit->contract_year) {
            $p['delivery_year'] = self::text('seller.rules.delivery_before_contract');
        }
        $main = $sub->mainType()->value;
        $outOfScope = $unit->features()->get()->reject(fn (Feature $f): bool => in_array($main, $f->applies_to, true) && $f->is_active);
        if ($outOfScope->isNotEmpty()) {
            $p['features'] = self::text('seller.rules.feature_scope');
        }

        return $p;
    }

    /**
     * @return array<string, string>
     */
    private static function step3Problems(Unit $unit): array
    {
        $p = [];
        foreach (['total_price', 'paid_amount', 'market_price_estimate'] as $key) {
            if ($unit->{$key} === null) {
                $p[$key] = self::text('seller.rules.required');
            }
        }
        if ($p !== []) {
            return $p;
        }
        $total = (int) $unit->total_price;
        $paid = (int) $unit->paid_amount;
        $remaining = $total - $paid;
        if ($paid > $total) {
            return ['paid_amount' => self::text('seller.rules.paid_over_total')];
        }

        if ($remaining > 0) {
            if ($unit->installment_amount === null) {
                $p['installment_amount'] = self::text('seller.rules.required');
            } elseif ($unit->installment_amount > $remaining) {
                $p['installment_amount'] = self::text('seller.rules.installment_over_remaining');
            }
            if (! $unit->installment_frequency instanceof InstallmentFrequency) {
                $p['installment_frequency'] = self::text('seller.rules.required');
            }
            if ($unit->next_due_date === null) {
                $p['next_due_date'] = self::text('seller.rules.required');
            } elseif (! $unit->has_overdue && $unit->next_due_date->startOfDay()->lte(CarbonImmutable::today())) {
                $p['next_due_date'] = self::text('seller.rules.due_date_past');
            }
        }
        if ($unit->delivery_payment !== null && $unit->delivery_payment > $remaining) {
            $p['delivery_payment'] = self::text('seller.rules.over_remaining');
        }
        if ($unit->accepts_less) {
            if ($unit->lowest_accepted_amount === null) {
                $p['lowest_accepted_amount'] = self::text('seller.rules.required');
            } elseif ($unit->lowest_accepted_amount >= $paid) {
                $p['lowest_accepted_amount'] = self::text('seller.rules.lowest_not_below_paid');
            }
        }
        if ($unit->has_overdue) {
            if ($unit->overdue_count === null) {
                $p['overdue_count'] = self::text('seller.rules.required');
            }
            if ($unit->overdue_amount === null) {
                $p['overdue_amount'] = self::text('seller.rules.required');
            } elseif ($unit->overdue_amount > $remaining) {
                $p['overdue_amount'] = self::text('seller.rules.over_remaining');
            }
            if (! $unit->overdue_settlement instanceof OverdueSettlement) {
                $p['overdue_settlement'] = self::text('seller.rules.required');
            }
        }

        return $p;
    }

    /**
     * @return array<string, string>
     */
    private function step4Problems(Unit $unit): array
    {
        $have = [];
        foreach ($unit->documents()->get(['id', 'type']) as $document) {
            $have[$document->type->value] = ($have[$document->type->value] ?? 0) + 1;
        }
        $needed = [DocumentType::Contract, DocumentType::Schedule, DocumentType::NationalIdFront, DocumentType::NationalIdBack];
        if ($this->settings->bool('receipts_required')) {
            $needed[] = DocumentType::Receipt;
        }
        $p = [];
        foreach ($needed as $type) {
            if (($have[$type->value] ?? 0) < 1) {
                $p['documents.'.$type->value] = self::text('seller.rules.required');
            }
        }

        return $p;
    }

    /**
     * @return array<string, string>
     */
    private function step5Problems(Unit $unit): array
    {
        $p = [];
        $description = (string) $unit->description;
        $length = mb_strlen($description);
        $min = $this->settings->int('description_min');
        $max = $this->settings->int('description_max');
        if ($length < $min || $length > $max) {
            $p['description'] = self::text('seller.rules.description_length', ['min' => $min, 'max' => $max]);
        } elseif (self::hasContactDetails($description)) {
            $p['description'] = self::text('seller.rules.contact_details');
        }
        $photos = $unit->photos()->where('kind', PhotoKind::Photo->value)->count();
        $minPhotos = $this->settings->int('photo_min_count');
        $maxPhotos = $this->settings->int('photo_max_count');
        if ($photos < $minPhotos || $photos > $maxPhotos) {
            $p['photos'] = self::text('seller.rules.photos_count', ['min' => $minPhotos, 'max' => $maxPhotos]);
        }

        return $p;
    }

    /**
     * Each place is a list choice or an "other" text, never both: sending one clears the other.
     *
     * @param  array<string, mixed>  $data
     */
    private static function pair(Unit $unit, array $data, string $name): void
    {
        $idKey = $name.'_id';
        $otherKey = $name.'_other';
        $slug = $data[$name] ?? null;
        $id = is_string($slug) && $slug !== '' ? self::idOf($name, $slug) : null;
        $hasOther = array_key_exists($otherKey, $data) && is_string($data[$otherKey]) && trim($data[$otherKey]) !== '';
        if ($id !== null && $hasOther) {
            throw ValidationException::withMessages([$otherKey => self::text('seller.rules.pair_one')]);
        }
        if ($id !== null) {
            $unit->forceFill([$idKey => $id, $otherKey => null]);
        } elseif ($hasOther) {
            $unit->forceFill([$idKey => null, $otherKey => trim((string) $data[$otherKey])]);
        } elseif (array_key_exists($name, $data) || array_key_exists($otherKey, $data)) {
            $unit->forceFill([$idKey => null, $otherKey => null]);
        }
        // Choosing a compound fills its area when the seller has not picked one yet.
        if ($name === 'compound' && $id !== null && $unit->area_id === null && blank($unit->area_other)) {
            $compound = Compound::query()->whereKey($id)->first(['id', 'area_id']);
            $unit->area_id = $compound?->area_id;
        }
    }

    /** @return int<0, max>|null */
    private static function idOf(string $name, string $slug): ?int
    {
        $model = match ($name) {
            'developer' => Developer::query()->where('slug', $slug)->first(['id']),
            'compound' => Compound::query()->where('slug', $slug)->first(['id']),
            default => Area::query()->where('slug', $slug)->first(['id']),
        };

        return $model?->id;
    }

    /**
     * @param  array<mixed>|null  $sides
     */
    private static function facadeBits(?array $sides): ?int
    {
        if ($sides === null || $sides === []) {
            return null;
        }
        $bits = 0;
        foreach ($sides as $side) {
            $bits |= is_string($side) ? (self::FACADE_BITS[$side] ?? 0) : 0;
        }

        return $bits;
    }

    /**
     * @param  array<mixed>  $codes
     * @return list<int>
     */
    private static function featureIds(array $codes): array
    {
        $codes = array_values(array_filter($codes, is_string(...)));
        $ids = [];
        foreach (Feature::query()->whereIn('code', $codes)->where('is_active', true)->get(['id']) as $feature) {
            $ids[] = $feature->id;
        }
        if (count($ids) !== count($codes)) {
            throw ValidationException::withMessages(['features' => self::text('seller.rules.feature_scope')]);
        }

        return $ids;
    }

    /**
     * @param  array<string, int|string>  $params
     */
    private static function text(string $key, array $params = []): string
    {
        $text = __($key, $params);

        return is_string($text) ? $text : $key;
    }
}
