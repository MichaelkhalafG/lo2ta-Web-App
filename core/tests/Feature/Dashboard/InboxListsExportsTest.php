<?php

use App\Enums\AdminRole as AdminRoleCode;
use App\Enums\UnitState;
use App\Models\ActivityLog;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\Compound;
use App\Models\ContactMessage;
use App\Models\Developer;
use App\Models\PartnershipRequest;
use App\Models\SiteText;
use App\Models\Unit;
use App\Models\User;
use App\Support\Uploads\UploadService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Part 7.6 (G5, G6, G16): the contact and partnership inboxes with the 12-month deletion of handled messages, areas,
| compounds and developers (an admin edits, an area code fixed once used), site texts (admin), and the CSV exports
| (admin only, every data field, no files, every export logged).
*/

beforeEach(function () {
    Mail::fake();
    Storage::fake(UploadService::PRIVATE_DISK);
    $this->seed(ReferenceSeeder::class);
    foreach (AdminRoleCode::cases() as $code) {
        AdminRole::query()->firstOrCreate(['code' => $code->value], ['name_ar' => $code->value, 'name_en' => $code->value]);
    }
    $this->admin = AdminUser::factory()->admin()->create();
    $this->reviewer = AdminUser::factory()->create();
});

it('works both inboxes for both roles and deletes handled messages after the retention period only (G5)', function () {
    $old = ContactMessage::query()->create(['name' => 'Old Sender', 'email' => 'old@example.test', 'message' => 'An old handled message.', 'state' => 'new']);
    $fresh = ContactMessage::query()->create(['name' => 'New Sender', 'email' => 'new@example.test', 'message' => 'A message still waiting.', 'state' => 'new']);
    $partner = PartnershipRequest::query()->create(['company' => 'Test Co', 'contact_name' => 'P Person', 'email' => 'p@example.test', 'phone' => '0223456789', 'message' => 'Partnership please.', 'state' => 'new']);

    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/inbox/contact')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Inbox/Index')->has('table.data', 2)->where('retentionMonths', 12));
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/inbox/partnership')->assertOk()->assertInertia(fn (Assert $p) => $p->has('table.data', 1));
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/inbox/contact/{$old->id}", ['handled' => true, 'staff_note' => 'Answered by phone'])->assertSessionHasNoErrors();
    $this->actingAs($this->reviewer, 'admin')->put("/dashboard/inbox/partnership/{$partner->id}", ['handled' => true])->assertSessionHasNoErrors();
    expect($old->refresh()->state)->toBe('handled')->and($old->getAttribute('handled_by'))->toBe($this->reviewer->id)
        ->and(ActivityLog::query()->where('action', 'contact_message.handled')->exists())->toBeTrue();

    $old->forceFill(['handled_at' => now()->subMonths(13)])->save();
    $this->artisan('messages:purge', ['--dry-run' => true])->assertSuccessful();
    expect(ContactMessage::query()->count())->toBe(2);
    $this->artisan('messages:purge')->assertSuccessful();
    expect(ContactMessage::query()->pluck('id')->all())->toBe([$fresh->id])
        ->and(PartnershipRequest::query()->count())->toBe(1)
        ->and(ActivityLog::query()->where('action', 'messages.purged')->value('after'))->toMatchArray(['contact' => 1, 'partnership' => 0]);
});

it('lets an admin edit areas, compounds and developers, a reviewer only see them, and fixes an area code once used (G16)', function () {
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/lists/areas')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Lists/Areas')->where('canEdit', false));
    $this->actingAs($this->reviewer, 'admin')->post('/dashboard/lists/developers', ['name_ar' => 'مطور', 'name_en' => 'Dev', 'is_active' => true])->assertForbidden();

    $this->actingAs($this->admin, 'admin')->post('/dashboard/lists/developers', ['name_ar' => 'مطور اختبار', 'name_en' => 'Test Dev Co', 'is_active' => true])->assertSessionHasNoErrors();
    $developer = Developer::query()->where('name_en', 'Test Dev Co')->sole();
    expect($developer->slug)->toBe('test-dev-co');

    $governorate = Area::query()->whereNull('parent_id')->firstOrFail();
    $this->actingAs($this->admin, 'admin')->post('/dashboard/lists/areas', ['parent_id' => $governorate->id, 'name_ar' => 'منطقة اختبار', 'name_en' => 'Test Area', 'code' => 'qz', 'is_active' => true])->assertSessionHasNoErrors();
    $area = Area::query()->where('name_en', 'Test Area')->sole();
    expect($area->code)->toBe('QZ');

    $this->actingAs($this->admin, 'admin')->post('/dashboard/lists/compounds', ['developer_id' => $developer->id, 'area_id' => $area->id, 'name_ar' => 'كمبوند', 'name_en' => 'Test Gardens', 'intro_ar' => 'نص', 'intro_en' => 'Text', 'is_active' => true])->assertSessionHasNoErrors();
    $compound = Compound::query()->where('name_en', 'Test Gardens')->sole();
    expect($compound->intro_is_placeholder)->toBeFalse()->and($compound->slug)->toBe('test-gardens');

    // Free while no sent unit uses it; fixed after.
    $this->actingAs($this->admin, 'admin')->put("/dashboard/lists/areas/{$area->id}", ['parent_id' => $governorate->id, 'name_ar' => 'منطقة اختبار', 'name_en' => 'Test Area', 'code' => 'QY', 'is_active' => true])->assertSessionHasNoErrors();
    Unit::factory()->create(['compound_id' => $compound->id, 'developer_id' => $developer->id, 'area_id' => $area->id, 'state' => UnitState::Submitted, 'serial' => 777]);
    $this->actingAs($this->admin, 'admin')->put("/dashboard/lists/areas/{$area->id}", ['parent_id' => $governorate->id, 'name_ar' => 'منطقة اختبار', 'name_en' => 'Test Area', 'code' => 'QX', 'is_active' => true])->assertSessionHasErrors('code');
    $this->actingAs($this->admin, 'admin')->put("/dashboard/lists/areas/{$area->id}", ['parent_id' => null, 'name_ar' => 'منطقة اختبار', 'name_en' => 'Test Area', 'code' => 'QY', 'is_active' => true])->assertSessionHasErrors('parent_id');
    expect($area->refresh()->code)->toBe('QY')
        ->and(ActivityLog::query()->where('actor_type', 'admin')->where('actor_id', $this->admin->id)->whereIn('action', ['developers.created', 'areas.created', 'compounds.created', 'areas.updated'])->count())->toBe(4);
});

it('lets only an admin edit the site texts, with their state', function () {
    $text = SiteText::query()->firstOrFail();
    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/site-texts')->assertForbidden();
    $this->actingAs($this->admin, 'admin')->get('/dashboard/site-texts')->assertOk()->assertInertia(fn (Assert $p) => $p->component('SiteTexts/Index')->has('texts'));
    $this->actingAs($this->admin, 'admin')->put("/dashboard/site-texts/{$text->id}", ['ar' => 'نص جديد', 'en' => 'New text', 'source' => 'client'])->assertSessionHasNoErrors();
    expect($text->refresh()->en)->toBe('New text')->and($text->source->value)->toBe('client')->and($text->is_placeholder)->toBeFalse()
        ->and($text->updated_by)->toBe($this->admin->id)
        ->and(ActivityLog::query()->where('action', 'site_texts.updated')->where('subject_id', $text->id)->exists())->toBeTrue();
    $this->actingAs($this->admin, 'admin')->put("/dashboard/site-texts/{$text->id}", ['ar' => 'x', 'en' => 'y', 'source' => 'nonsense'])->assertSessionHasErrors('source');
});

it('exports every data field to admins only, never the files or the internal keys, and logs each export (G6)', function () {
    $seller = User::factory()->create(['name' => 'Export Seller']);
    $unit = completeDraft($seller, ['contract_number' => 'EXP-CONTRACT-1']);
    $unit->forceFill(['state' => UnitState::Submitted, 'serial' => 4242, 'contract_key' => 'secret-key-hash', 'active_contract_key' => 'secret-key-hash'])->save();

    $this->actingAs($this->reviewer, 'admin')->get('/dashboard/units?export=csv')->assertForbidden();
    foreach (['units', 'deals', 'commissions', 'requests', 'people'] as $list) {
        $response = $this->actingAs($this->admin, 'admin')->get("/dashboard/{$list}?export=csv")->assertOk();
        $csv = $response->streamedContent();
        if ($list === 'units') {
            expect($csv)->toContain('EXP-CONTRACT-1')->toContain('Export Seller')->not->toContain('secret-key-hash')->not->toContain('documents/');
        }
        if ($list === 'people') {
            expect($csv)->toContain($seller->email);
        }
    }
    $lines = ActivityLog::query()->where('action', 'export.csv')->get();
    expect($lines)->toHaveCount(5)->and($lines->every(fn ($l) => $l->actor_id === $this->admin->id))->toBeTrue()
        ->and($lines->pluck('after.list')->all())->toBe(['units', 'deals', 'commissions', 'requests', 'people'])
        ->and($lines->first()->after['rows'])->toBe(1);
});
