<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sample developers and compounds for local work. SCRATCH ONLY: refuses to run unless the database name ends in
 * _scratch or _test. Names are invented ("Sample developer 1") so nothing here can be mistaken for real data.
 */
class SampleSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        $database = is_string($connection) ? config("database.connections.{$connection}.database") : null;
        if (! is_string($database) || preg_match('/_(scratch|test)$/', $database) !== 1) {
            throw new RuntimeException('STOP - SampleSeeder runs only on a *_scratch or *_test database.');
        }

        $areas = Area::query()->whereNotNull('parent_id')->orderBy('id')->limit(4)->get();
        if ($areas->isEmpty()) {
            throw new RuntimeException('STOP - run ReferenceSeeder first (no areas).');
        }

        DB::transaction(function () use ($areas): void {
            $added = 0;
            foreach (range(1, 3) as $d) {
                $developer = Developer::query()->firstOrCreate(
                    ['slug' => "sample-developer-{$d}"],
                    ['name_ar' => "مطور تجريبي {$d}", 'name_en' => "Sample developer {$d}"],
                );
                foreach (range(1, 2) as $c) {
                    $area = $areas->values()->get(($d + $c) % $areas->count());
                    if (! $area instanceof Area) {
                        throw new RuntimeException('STOP - area list changed while seeding.');
                    }
                    $compound = Compound::query()->firstOrCreate(
                        ['slug' => "sample-compound-{$d}-{$c}"],
                        [
                            'developer_id' => $developer->id,
                            'area_id' => $area->id,
                            'name_ar' => "مشروع تجريبي {$d}-{$c}",
                            'name_en' => "Sample compound {$d}-{$c}",
                        ],
                    );
                    $added += $compound->wasRecentlyCreated ? 1 : 0;
                }
            }
            $this->command?->line("  sample compounds: {$added} added");
        });
    }
}
