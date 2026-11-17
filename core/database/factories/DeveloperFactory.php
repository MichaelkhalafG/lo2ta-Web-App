<?php

namespace Database\Factories;

use App\Models\Developer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Developer>
 */
class DeveloperFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'name_ar' => "مطور {$n}",
            'name_en' => "Developer {$n}",
            'slug' => "developer-{$n}",
            'is_active' => true,
        ];
    }
}
