<?php

use App\Enums\AdminRole;
use App\Enums\DeclarationKind;
use App\Enums\DeliveryStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\FeatureGroup;
use App\Enums\Finishing;
use App\Enums\InstallmentFrequency;
use App\Enums\MailState;
use App\Enums\MainType;
use App\Enums\PhotoKind;
use App\Enums\ReviewOutcome;
use App\Enums\SubType;
use App\Enums\TextSource;
use App\Enums\UnitState;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\Feature;
use App\Models\SiteText;
use Database\Seeders\ReferenceSeeder;
use Database\Seeders\SampleSeeder;

it('has the 25 approved sub-types mapped to the four main types', function () {
    $counts = collect(SubType::cases())->countBy(fn (SubType $s) => $s->mainType()->value)->all();

    expect(SubType::cases())->toHaveCount(25)
        ->and($counts)->toBe(['residential' => 13, 'commercial' => 6, 'administrative' => 2, 'land' => 4]);
});

it('hides the fields that do not apply (PLAN 2.2.3)', function () {
    expect(SubType::StandaloneVilla->hasFloor())->toBeFalse()
        ->and(SubType::IVilla->hasFloor())->toBeTrue()
        ->and(SubType::Shop->hasRooms())->toBeFalse()
        ->and(SubType::Studio->fixedBedrooms())->toBe(0)
        ->and(SubType::ResidentialLand->hasFinishing())->toBeFalse()
        ->and(SubType::RestaurantCafe->hasGarden())->toBeTrue()
        ->and(SubType::Penthouse->hasRoof())->toBeTrue()
        ->and(SubType::Penthouse->hasGarden())->toBeFalse();
});

it('labels every code of every enum in Arabic and English', function () {
    $enums = [UnitState::class, MainType::class, SubType::class, Finishing::class, DeliveryStatus::class,
        InstallmentFrequency::class, DocumentType::class, DocumentStatus::class, ReviewOutcome::class,
        DeclarationKind::class, PhotoKind::class, FeatureGroup::class, AdminRole::class, MailState::class,
        TextSource::class];

    foreach ($enums as $enum) {
        foreach ($enum::cases() as $case) {
            foreach (['ar', 'en'] as $locale) {
                expect($case->label($locale))->not->toStartWith('enums.', "{$enum}::{$case->name} has no {$locale} label");
            }
            expect($case->label('ar'))->not->toBe($case->label('en'));
        }
    }
});

it('seeds reference data add-only and leaves dashboard edits alone', function () {
    $this->seed(ReferenceSeeder::class);

    expect(Area::query()->whereNull('parent_id')->count())->toBe(7)
        ->and(Area::query()->whereNotNull('parent_id')->whereNull('code')->count())->toBe(0)
        ->and(Feature::query()->count())->toBe(35)
        ->and(SiteText::query()->where('is_placeholder', true)->count())->toBe(SiteText::query()->count());

    Feature::query()->where('code', 'gym')->update(['name_en' => 'Fitness centre']);
    $this->seed(ReferenceSeeder::class);

    expect(Feature::query()->where('code', 'gym')->value('name_en'))->toBe('Fitness centre')
        ->and(Feature::query()->count())->toBe(35);
});

it('stores the site name and the Arabic seed texts with the right code points', function () {
    $this->seed(ReferenceSeeder::class);

    $label = SiteText::query()->where('key', 'seller.photos.project_image_label')->value('ar');
    expect($label)->toBe("\u{0635}\u{0648}\u{0631}\u{0629} \u{0645}\u{0646} \u{0627}\u{0644}\u{0645}\u{0634}\u{0631}\u{0648}\u{0639}")
        ->and(config('locales.supported.ar.site_name'))->toBe("\u{0644}\u{0642}\u{0637}\u{0629}");
});

it('refuses to seed sample data outside a scratch or test database', function () {
    $this->seed(ReferenceSeeder::class);
    $connection = config('database.default');
    config(["database.connections.{$connection}.database" => 'lo2ta_production']);

    expect(fn () => $this->seed(SampleSeeder::class))->toThrow(RuntimeException::class, 'STOP');
});

it('creates a staff account only with a typed password of 12 or more characters', function () {
    $this->seed(ReferenceSeeder::class);

    $this->artisan('admin:create', ['email' => 'boss@example.test', 'name' => 'Boss', '--role' => 'admin'])
        ->expectsQuestion('Password (at least 12 characters)', 'short')
        ->expectsQuestion('Password again', 'short')
        ->assertFailed();

    $this->artisan('admin:create', ['email' => 'boss@example.test', 'name' => 'Boss', '--role' => 'admin'])
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-enough-pass')
        ->expectsQuestion('Password again', 'a-long-enough-pass')
        ->assertSuccessful();

    expect(AdminUser::query()->where('email', 'boss@example.test')->sole()->isAdmin())->toBeTrue();
});
