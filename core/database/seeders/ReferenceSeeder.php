<?php

namespace Database\Seeders;

use App\Enums\AdminRole as AdminRoleCode;
use App\Enums\FeatureGroup;
use App\Enums\TextSource;
use App\Models\AdminRole;
use App\Models\Area;
use App\Models\Feature;
use App\Models\SiteText;
use App\Support\Settings\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reference data every environment needs: roles, settings defaults, areas, features and placeholder texts.
 * Add-only: a row that exists (matched by its code, key or slug) is never changed, because the dashboard owns
 * these rows after the first seed (handover 2.3). Safe to run again.
 */
class ReferenceSeeder extends Seeder
{
    public function run(Settings $settings): void
    {
        DB::transaction(function () use ($settings): void {
            $this->roles();
            $created = $settings->seedDefaults();
            $this->command?->line("  settings: {$created} added");
            $this->areas();
            $this->features();
            $this->siteTexts();
        });
    }

    private function roles(): void
    {
        $names = [
            AdminRoleCode::Admin->value => ['مدير', 'Admin'],
            AdminRoleCode::Reviewer->value => ['مراجع', 'Reviewer'],
        ];
        $added = 0;
        foreach ($names as $code => [$ar, $en]) {
            $role = AdminRole::query()->firstOrNew(['code' => $code]);
            if (! $role->exists) {
                $role->fill(['name_ar' => $ar, 'name_en' => $en])->save();
                $added++;
            }
        }
        $this->command?->line("  roles: {$added} added");
    }

    private function areas(): void
    {
        /** @var list<array{slug: string, ar: string, en: string, areas: list<array{slug: string, code: string, ar: string, en: string}>}> $rows */
        $rows = require database_path('data/areas.php');
        $added = 0;
        foreach ($rows as $gSort => $gov) {
            $parent = Area::query()->firstOrNew(['slug' => $gov['slug']]);
            if (! $parent->exists) {
                $parent->fill(['name_ar' => $gov['ar'], 'name_en' => $gov['en'], 'sort' => $gSort])->save();
                $added++;
            }
            foreach ($gov['areas'] as $aSort => $area) {
                $row = Area::query()->firstOrNew(['slug' => $area['slug']]);
                if (! $row->exists) {
                    $row->fill([
                        'parent_id' => $parent->id,
                        'code' => $area['code'],
                        'name_ar' => $area['ar'],
                        'name_en' => $area['en'],
                        'sort' => $aSort,
                    ])->save();
                    $added++;
                }
            }
        }
        $this->command?->line("  areas: {$added} added");
    }

    private function features(): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: list<string>}> $rows */
        $rows = require database_path('data/features.php');
        $added = 0;
        foreach ($rows as $sort => [$group, $code, $ar, $en, $appliesTo]) {
            $row = Feature::query()->firstOrNew(['code' => $code]);
            if (! $row->exists) {
                $row->fill([
                    'group' => FeatureGroup::from($group),
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'applies_to' => $appliesTo,
                    'sort' => $sort,
                ])->save();
                $added++;
            }
        }
        $this->command?->line("  features: {$added} added");
    }

    private function siteTexts(): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> $rows */
        $rows = require database_path('data/site_texts.php');
        $added = 0;
        foreach ($rows as [$key, $group, $ar, $en, $note]) {
            $row = SiteText::query()->firstOrNew(['key' => $key]);
            if (! $row->exists) {
                $row->fill([
                    'group' => $group,
                    'ar' => $ar,
                    'en' => $en,
                    'is_placeholder' => true,
                    'source' => TextSource::Placeholder,
                    'note' => $note,
                ])->save();
                $added++;
            }
        }
        $this->command?->line("  site texts: {$added} added");
    }
}
