<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compound>
 */
class CompoundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'developer_id' => Developer::factory(),
            'area_id' => fn () => self::area($n)->id,
            'name_ar' => "مشروع {$n}",
            'name_en' => "Compound {$n}",
            'slug' => "compound-{$n}",
            'is_active' => true,
        ];
    }

    private static function area(int $n): Area
    {
        $governorate = Area::query()->firstOrCreate(
            ['slug' => 'test-governorate'],
            ['name_ar' => 'محافظة اختبار', 'name_en' => 'Test governorate'],
        );

        return Area::query()->create([
            'parent_id' => $governorate->id,
            // Four characters: real area codes are two letters (PLAN 3.3), so a test area never takes one.
            'code' => 'Z'.str_pad(strtoupper(base_convert((string) ($n % 46656), 10, 36)), 3, '0', STR_PAD_LEFT),
            'name_ar' => "منطقة {$n}",
            'name_en' => "Area {$n}",
            'slug' => "area-{$n}",
        ]);
    }
}
