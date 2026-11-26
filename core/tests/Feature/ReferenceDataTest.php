<?php

use App\Enums\AdminRole;
use App\Enums\DeclarationKind;
use App\Enums\DeliveryPreference;
use App\Enums\DeliveryStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\FeatureGroup;
use App\Enums\Finishing;
use App\Enums\InstallmentFrequency;
use App\Enums\MailState;
use App\Enums\MainType;
use App\Enums\OverdueSettlement;
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
        TextSource::class, OverdueSettlement::class, DeliveryPreference::class];

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

    $governorates = Area::query()->whereNull('parent_id')->pluck('id');
    $areas = Area::query()->whereIn('parent_id', $governorates)->get();
    $districts = Area::query()->whereIn('parent_id', $areas->pluck('id'))->get();

    // Three levels (PLAN 3.3): the code sits on the area; governorates and districts have none.
    expect($governorates)->toHaveCount(10)
        ->and($areas)->toHaveCount(42)
        ->and($areas->whereNull('code'))->toHaveCount(0)
        ->and($districts)->toHaveCount(19)
        ->and($districts->whereNotNull('code'))->toHaveCount(0)
        ->and(Area::query()->whereIn('id', $governorates)->whereNotNull('code')->count())->toBe(0)
        ->and(Area::query()->count())->toBe(71)
        ->and(Feature::query()->count())->toBe(35)
        // K7: no placeholder left; the legal texts (PLAN 6 rows 6, 7, 8, 8a, 9, 10) and the FAQ answer that uses the
        // legal notes await legal review; the About page is the client's own text (Michael 2026-11-26); every other
        // text awaits the client's review.
        ->and(SiteText::query()->where('is_placeholder', true)->count())->toBe(0)
        ->and(SiteText::query()->where('source', TextSource::Placeholder->value)->count())->toBe(0)
        ->and(SiteText::query()->where('source', TextSource::Client->value)->orderBy('key')->pluck('key')->all())->toBe([
            'about.enas.body', 'about.enas.name', 'about.enas.role', 'about.enas.title', 'about.how', 'about.how_title',
            'about.intro', 'about.story', 'about.tawfik.body', 'about.tawfik.name', 'about.tawfik.role', 'about.tawfik.title',
        ])
        // Eng. Enas's story in the feminine (Michael 2026-11-26).
        ->and(SiteText::query()->where('key', 'about.enas.body')->value('ar'))->toContain('كنت مؤمنة')->not->toContain('مؤمن إن')
        ->and(SiteText::query()->where('key', 'about.enas.role')->value('ar'))->toBe('الشريكة المحامية')
        ->and(SiteText::query()->where('key', 'home.trust.partner_lawyer')->value('ar'))->toBe('الشريكة المحامية')
        ->and(SiteText::query()->where('key', 'home.trust.step1_sub')->value('ar'))->toBe('بإشراف الشريكة المحامية')
        ->and(SiteText::query()->where('key', 'home.trust.body')->value('ar'))->toContain('ومحامية تحوّلت لمستثمرة عقارية')
        // No text refers to Eng. Enas in the masculine (Michael 2026-11-26).
        ->and(SiteText::query()->where('ar', 'like', '%محامٍ%')->orWhere('ar', 'like', '%الشريك المحامي%')->orWhere('ar', 'like', '%المحامي الشريك%')->count())->toBe(0)
        ->and(SiteText::query()->where('source', TextSource::LegalReview->value)->orderBy('key')->pluck('key')->all())->toBe([
            'buyer.fee_consent', 'calculator.disclaimer', 'exit_report.disclaimer_text', 'faq.a10', 'faq.q10',
            'legal.privacy.body', 'legal.privacy.id_retention', 'legal.terms.body',
            'seller.declaration.data_matches', 'seller.declaration.owner_not_broker', 'seller.declaration.terms_privacy',
            'seller.no_over.body', 'seller.no_over.confirm', 'seller.no_over.title', 'seller.notice.owner_only',
        ])
        // Nothing we wrote still says it is a placeholder.
        ->and(SiteText::query()->where('ar', 'like', '%نص مؤقت%')->orWhere('en', 'like', '%Placeholder%')->count())->toBe(0);

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
