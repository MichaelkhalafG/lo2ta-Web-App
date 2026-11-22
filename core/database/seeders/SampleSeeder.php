<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\Finishing;
use App\Enums\InstallmentFrequency;
use App\Enums\OverdueSettlement;
use App\Enums\PhotoKind;
use App\Enums\SubType;
use App\Enums\UnitState;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\Unit;
use App\Models\User;
use App\Support\Listing\UnitCode;
use App\Support\Uploads\PhotoPublisher;
use App\Support\Uploads\UploadService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $this->sampleUnits();
    }

    /**
     * Published sample units with drawn photos, so the listing, unit and compound pages can be seen before phase 7
     * publishes real ones. Invented figures and a sample seller; the seller has no password anyone knows.
     */
    private function sampleUnits(): void
    {
        if (Unit::query()->where('contract_number', 'like', 'SAMPLE-%')->exists()) {
            $this->command?->line('  sample units: already there');

            return;
        }
        $seller = User::query()->firstOrCreate(['email' => 'sample.seller@example.test'], [
            'name' => 'بائع تجريبي', 'phone' => '01000000000', 'password' => Hash::make(Str::random(40)), 'email_verified_at' => now(),
        ]);
        $compounds = Compound::query()->where('slug', 'like', 'sample-compound-%')->with('area.parent')->get();
        $max = Unit::query()->max('serial');
        $next = (is_numeric($max) ? (int) $max : 0) + 100;
        $shapes = [
            [SubType::Apartment, 165, 3, 4_848_000, 1_320_000, 126_000, InstallmentFrequency::Quarterly, 7],
            [SubType::Duplex, 240, 4, 9_200_000, 2_760_000, 230_000, InstallmentFrequency::Quarterly, 7],
            [SubType::Studio, 70, 0, 2_100_000, 630_000, 24_500, InstallmentFrequency::Monthly, 5],
            [SubType::Townhouse, 210, 3, 11_500_000, 3_450_000, 268_000, InstallmentFrequency::Quarterly, 6],
            [SubType::Chalet, 110, 2, 5_300_000, 2_120_000, 132_500, InstallmentFrequency::Quarterly, 6],
            [SubType::Office, 85, 0, 4_100_000, 1_025_000, 96_000, InstallmentFrequency::Quarterly, 8],
            [SubType::Shop, 60, 0, 6_400_000, 1_920_000, 186_000, InstallmentFrequency::Quarterly, 6],
            [SubType::Penthouse, 190, 3, 8_700_000, 3_045_000, 202_000, InstallmentFrequency::Quarterly, 7],
        ];
        $made = 0;
        foreach ($compounds as $i => $compound) {
            foreach ([0, 1] as $j) {
                [$sub, $size, $rooms, $total, $paid, $installment, $frequency, $years] = $shapes[($i * 2 + $j) % count($shapes)];
                $unit = new Unit;
                $unit->forceFill([
                    'seller_id' => $seller->id, 'state' => UnitState::Published, 'step_reached' => 6, 'serial' => $next++,
                    'developer_id' => $compound->developer_id, 'compound_id' => $compound->id, 'area_id' => $compound->area_id,
                    'main_type' => $sub->mainType(), 'sub_type' => $sub, 'contract_number' => 'SAMPLE-'.Str::random(8), 'unit_number' => 'S-'.$next,
                    'contract_year' => 2023, 'contract_month' => 3 + $j, 'size_m2' => (string) $size,
                    'floor' => $sub->hasFloor() ? (string) (2 + $j) : null, 'bedrooms' => $sub->hasRooms() ? $rooms : null,
                    'bathrooms' => $sub->hasRooms() ? max(1, $rooms - 1) : null,
                    'finishing' => $sub->hasFinishing() ? Finishing::SemiFinished : null, 'delivery_status' => DeliveryStatus::UnderConstruction,
                    'delivery_year' => 2027 + $j, 'delivery_month' => 12, 'facade' => 1 + 2 * $j,
                    'description' => 'وحدة تجريبية للعرض فقط، أرقامها مخترعة ولا تخص أي عقد حقيقي. Sample unit for display only; invented figures.',
                    'total_price' => $total, 'paid_amount' => $paid, 'market_price_estimate' => (int) round($total * 1.4, -4),
                    'installment_amount' => $installment, 'installment_frequency' => $frequency,
                    'next_due_date' => now()->addDays(20 + 9 * $j)->toDateString(), 'years_left' => $years,
                    'accepts_less' => $j === 1, 'lowest_accepted_amount' => $j === 1 ? (int) round($paid * 0.92, -4) : null,
                    'has_overdue' => $i === 1 && $j === 0, 'overdue_count' => $i === 1 && $j === 0 ? 1 : null,
                    'overdue_amount' => $i === 1 && $j === 0 ? $installment : null,
                    'overdue_settlement' => $i === 1 && $j === 0 ? OverdueSettlement::Deducted : null,
                    'deal_tag' => $i === 0 && $j === 0, 'verified_at' => $j === 0 ? now() : null,
                    'submitted_at' => now()->subDays(10), 'published_at' => now()->subDays(9 - $i - $j),
                ]);
                $unit->setRelation('compound', $compound);
                $unit->setRelation('area', $compound->area);
                UnitCode::assign($unit);
                $unit->save();
                foreach ([0, 1, 2] as $n) {
                    $this->samplePhoto($unit, $n);
                }
                $made++;
            }
        }
        $this->command?->line("  sample units: {$made} published");
    }

    /** A drawn placeholder image (no real place), stored privately and published like a reviewed photo. */
    private function samplePhoto(Unit $unit, int $n): void
    {
        $image = imagecreatetruecolor(1600, 1200);
        $shade = [[20, 26, 38], [30, 40, 58], [44, 38, 30]][$n % 3];
        imagefilledrectangle($image, 0, 0, 1599, 1199, (int) imagecolorallocate($image, ...$shade));
        $line = (int) imagecolorallocate($image, 214, 172, 85);
        for ($x = 120; $x < 1600; $x += 240) {
            imageline($image, $x, 200, $x, 1000, $line);
        }
        imagerectangle($image, 120, 200, 1480, 1000, $line);
        ob_start();
        imagewebp($image, null, 80);
        $bytes = (string) ob_get_clean();
        $path = UploadService::randomName('photos', 'webp');
        Storage::disk(UploadService::PRIVATE_DISK)->put($path, $bytes);
        $photo = $unit->photos()->create([
            'kind' => PhotoKind::Photo, 'path' => $path, 'sort' => $n + 1, 'width' => 1600, 'height' => 1200,
            'bytes' => strlen($bytes), 'is_project_image' => $n === 2, 'is_hidden' => false,
        ]);
        app(PhotoPublisher::class)->publish($photo);
    }
}
