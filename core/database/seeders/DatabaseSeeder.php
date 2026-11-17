<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed` = reference data (safe everywhere, add-only) + sample data (refuses outside scratch/test).
 * No staff account is seeded: create one with `php artisan admin:create` (the password is typed at a prompt).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceSeeder::class,
            SampleSeeder::class,
        ]);
    }
}
