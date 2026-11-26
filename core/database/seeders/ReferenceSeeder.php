<?php

namespace Database\Seeders;

use App\Enums\AdminRole as AdminRoleCode;
use App\Enums\FeatureGroup;
use App\Enums\TextSource;
use App\Models\AdminRole;
use App\Models\Area;
use App\Models\Feature;
use App\Models\MarketValue;
use App\Models\SiteText;
use App\Support\Settings\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reference data every environment needs: roles, settings defaults, areas, features, placeholder texts and the
 * researched market values with their sources (PLAN 4.3).
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
            $this->marketValues();
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
        /** @var list<array{slug: string, ar: string, en: string, areas: list<array{slug: string, code: string, ar: string, en: string, districts: list<array{slug: string, ar: string, en: string}>}>}> $rows */
        $rows = require database_path('data/areas.php');
        $added = 0;
        $add = function (string $slug, array $values) use (&$added): Area {
            $row = Area::query()->firstOrNew(['slug' => $slug]);
            if (! $row->exists) {
                /** @var array<string, mixed> $values */
                $row->fill($values)->save();
                $added++;
            }

            return $row;
        };

        // Three levels (PLAN 3.3): governorate > area (carries the code) > district.
        foreach ($rows as $gSort => $gov) {
            $governorate = $add($gov['slug'], ['name_ar' => $gov['ar'], 'name_en' => $gov['en'], 'sort' => $gSort]);
            foreach ($gov['areas'] as $aSort => $area) {
                $parent = $add($area['slug'], [
                    'parent_id' => $governorate->id,
                    'code' => $area['code'],
                    'name_ar' => $area['ar'],
                    'name_en' => $area['en'],
                    'sort' => $aSort,
                ]);
                foreach ($area['districts'] as $dSort => $district) {
                    $add($district['slug'], [
                        'parent_id' => $parent->id,
                        'name_ar' => $district['ar'],
                        'name_en' => $district['en'],
                        'sort' => $dSort,
                    ]);
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

    /**
     * Our texts (K7, Michael 2026-11-26): every row is written by us and waits for the client's review ('ours'); a
     * row marked 'legal' waits for legal review first (PLAN 6 rows 6, 7, 8, 8a, 9, 10 and FAQ pair 10); a row marked
     * 'client' is the client's own text (the About page, Michael 2026-11-26).
     */
    private function siteTexts(): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5?: string}> $seller */
        $seller = require database_path('data/site_texts.php');
        /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5?: string}> $pages */
        $pages = require database_path('data/site_texts_pages.php');
        $rows = array_merge($seller, $pages);
        $added = 0;
        foreach ($rows as $text) {
            [$key, $group, $ar, $en, $note] = $text;
            $row = SiteText::query()->firstOrNew(['key' => $key]);
            if (! $row->exists) {
                $row->fill([
                    'group' => $group,
                    'ar' => $ar,
                    'en' => $en,
                    'is_placeholder' => false,
                    'source' => match ($text[5] ?? null) {
                        'legal' => TextSource::LegalReview,
                        'client' => TextSource::Client,
                        default => TextSource::Ours,
                    },
                    'note' => $note,
                ])->save();
                $added++;
            }
        }
        $this->command?->line("  site texts: {$added} added");
    }

    /** A missing value is added with its sources, provisional (not confirmed); an existing one is never touched. */
    private function marketValues(): void
    {
        /** @var array<string, array{checked_on: string, note: string, confirmed_at?: string, sources: list<array<string, mixed>>}> $rows */
        $rows = require database_path('data/market_values.php');
        $added = 0;
        foreach ($rows as $key => $row) {
            $value = MarketValue::query()->firstOrNew(['key' => $key]);
            if ($value->exists) {
                continue;
            }
            $value->fill(['checked_on' => $row['checked_on'], 'note' => $row['note'], 'confirmed_at' => $row['confirmed_at'] ?? null])->save();
            foreach ($row['sources'] as $sort => $source) {
                $value->sources()->create([...$source, 'counted' => $source['counted'] ?? true, 'sort' => $sort]);
            }
            $added++;
        }
        $this->command?->line("  market values: {$added} added");
    }
}
