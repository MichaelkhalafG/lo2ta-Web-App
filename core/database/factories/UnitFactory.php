<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\Finishing;
use App\Enums\InstallmentFrequency;
use App\Enums\MainType;
use App\Enums\SubType;
use App\Enums\UnitState;
use App\Models\Compound;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A complete residential unit. ->published() gives it a serial, a code and a slug as the publish step will.
 *
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'state' => UnitState::Draft,
            'compound_id' => Compound::factory(),
            'developer_id' => fn (array $a) => Compound::query()->whereKey($a['compound_id'])->firstOrFail()->developer_id,
            'area_id' => fn (array $a) => Compound::query()->whereKey($a['compound_id'])->firstOrFail()->area_id,
            'main_type' => MainType::Residential,
            'sub_type' => SubType::Apartment,
            'contract_number' => 'C-'.fake()->unique()->numerify('######'),
            'unit_number' => 'B'.fake()->numberBetween(1, 40).'-'.fake()->unique()->numberBetween(100, 99999),
            'contract_year' => 2023,
            'size_m2' => '145.00',
            'floor' => '3',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'finishing' => Finishing::SemiFinished,
            'delivery_status' => DeliveryStatus::UnderConstruction,
            'delivery_year' => 2027,
            'facade' => 1,
            'description' => 'وحدة تجريبية للاختبار فقط، بدون بيانات حقيقية. Test unit only, no real data.',
            'total_price' => 6_000_000,
            'paid_amount' => 1_500_000,
            'market_price_estimate' => 8_000_000,
            'installment_amount' => 112_500,
            'installment_frequency' => InstallmentFrequency::Quarterly,
            'next_due_date' => now()->addMonths(2)->toDateString(),
            'years_left' => 10,
            'accepts_less' => false,
            'has_overdue' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(function (): array {
            // Unique on its own: a counter plus a random number could add up to the same serial twice.
            $serial = fake()->unique()->numberBetween(1000, 999_999);

            return [
                'state' => UnitState::Published,
                'serial' => $serial,
                'code' => sprintf('LK-TS-%04d', $serial),
                'slug' => sprintf('test-unit-lk-ts-%04d', $serial),
                'submitted_at' => now()->subDays(3),
                'published_at' => now()->subDay(),
            ];
        });
    }
}
