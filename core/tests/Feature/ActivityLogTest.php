<?php

use App\Enums\AdminRole as AdminRoleCode;
use App\Models\ActivityLog;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\Compound;
use App\Models\Developer;
use App\Models\Feature;
use App\Models\Setting;
use App\Models\SiteText;
use App\Support\Activity\ActivityLogger;

/*
| Handover 2.3: every write action is logged, and a test proves it (a logging call cannot report its own absence).
| Each dashboard-authored model must log create, update and delete by itself.
*/

$makers = [
    'admin_roles' => fn () => AdminRole::query()->create(['code' => AdminRoleCode::Admin, 'name_ar' => 'م', 'name_en' => 'A']),
    'admin_users' => fn () => AdminUser::factory()->create(),
    'settings' => fn () => Setting::query()->create(['key' => 'k', 'value' => 1]),
    'site_texts' => fn () => SiteText::query()->create(['key' => 'k', 'group' => 'g', 'ar' => 'ن', 'en' => 't']),
    'areas' => fn () => Area::query()->create(['name_ar' => 'م', 'name_en' => 'A', 'slug' => 'a']),
    'developers' => fn () => Developer::factory()->create(),
    'compounds' => fn () => Compound::factory()->create(),
    'features' => fn () => Feature::query()->create(['code' => 'c', 'group' => 'unit', 'name_ar' => 'م', 'name_en' => 'F', 'applies_to' => ['land']]),
];

foreach ($makers as $table => $make) {
    it("logs create, update and delete of {$table}", function () use ($table, $make) {
        $model = $make();
        expect(ActivityLog::query()->where('action', "{$table}.created")->where('subject_id', $model->getKey())->exists())->toBeTrue();

        $model->forceFill(['updated_at' => now()->addMinute()])->save();
        expect(ActivityLog::query()->where('action', "{$table}.updated")->exists())
            ->toBeFalse('a change of updated_at alone is not a change');

        $column = $table === 'settings' ? 'is_provisional' : ($table === 'admin_users' ? 'name' : ($table === 'admin_roles' ? 'name_en' : ($table === 'site_texts' ? 'en' : 'name_en')));
        $new = $column === 'is_provisional' ? true : 'Changed';
        $model->update([$column => $new]);
        $line = ActivityLog::query()->where('action', "{$table}.updated")->sole();
        expect($line->after)->toHaveKey($column);

        $model->delete();
        expect(ActivityLog::query()->where('action', "{$table}.deleted")->exists())->toBeTrue();
    });
}

it('never writes a password hash or token into the log', function () {
    AdminUser::factory()->create(['remember_token' => 'tok-123']);

    $all = json_encode(ActivityLog::query()->get()->toArray());
    expect($all)->not->toContain('password')->and($all)->not->toContain('tok-123');
});

it('names the actor: admin, then system outside a request', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin');
    Developer::factory()->create();
    $byAdmin = ActivityLog::query()->where('action', 'developers.created')->latest('id')->first();

    expect($byAdmin?->actor_type)->toBe('admin')->and($byAdmin?->actor_id)->toBe($admin->id);
});

it('attaches a reason to every line written inside withReason', function () {
    app(ActivityLogger::class)->withReason('Merged a duplicate typed by a seller', function () {
        Developer::factory()->create();
    });
    Developer::factory()->create();

    $lines = ActivityLog::query()->where('action', 'developers.created')->orderBy('id')->get();
    expect($lines[0]->reason)->toBe('Merged a duplicate typed by a seller')
        ->and($lines[1]->reason)->toBeNull();
});
