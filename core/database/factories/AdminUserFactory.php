<?php

namespace Database\Factories;

use App\Enums\AdminRole as AdminRoleCode;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<AdminUser>
 */
class AdminUserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_role_id' => fn () => self::role(AdminRoleCode::Reviewer)->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('test-password-123'),
            'locale' => 'ar',
            'is_active' => true,
            'remember_token' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['admin_role_id' => self::role(AdminRoleCode::Admin)->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    private static function role(AdminRoleCode $code): AdminRole
    {
        return AdminRole::query()->firstOrCreate(
            ['code' => $code->value],
            ['name_ar' => $code->value, 'name_en' => $code->value],
        );
    }
}
