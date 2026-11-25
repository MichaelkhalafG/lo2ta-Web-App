<?php

use Illuminate\Support\Arr;

/*
| Every t('key') a dashboard screen asks for must exist in BOTH lang/ar/dashboard.php and lang/en/dashboard.php.
| The fallback in a t() call keeps a half-finished screen readable; this test is what stops it from shipping.
| Keys built at run time (`errors.${status}.title`, `activity.actor_types.${type}`) are listed explicitly.
*/

function dashboardKeysUsed(): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js')));
    foreach ($files as $file) {
        if (! $file->isFile() || ! preg_match('/\.tsx?$/', $file->getFilename())) {
            continue;
        }
        preg_match_all('/\bt\(\s*[\'"`]([a-z0-9_.]+)[\'"`]/', (string) file_get_contents($file->getPathname()), $m);
        array_push($keys, ...$m[1]);
    }

    foreach ([403, 404, 419, 500, 503] as $status) {
        $keys[] = "errors.{$status}.title";
        $keys[] = "errors.{$status}.body";
    }
    foreach (['admin', 'user', 'system'] as $type) {
        $keys[] = "activity.actor_types.{$type}";
    }
    // Calculator settings (Phase 6): per value and per choice, built from config/market_values.php and settings.
    foreach (array_keys((array) config('market_values.values')) as $value) {
        $keys[] = "calculator.explain.{$value}";
        $keys[] = "calculator.values.{$value}";
    }
    foreach (['penalty_basis' => ['total', 'paid'], 'refund_rule' => ['lump', 'mirror', 'fixed'], 'transfer_fee_mode' => ['pct', 'fixed']] as $setting => $options) {
        foreach ($options as $option) {
            $keys[] = "calculator.options.{$setting}.{$option}";
        }
    }

    return array_values(array_unique($keys));
}

it('has every dashboard key in Arabic and English', function () {
    $keys = dashboardKeysUsed();
    expect(count($keys))->toBeGreaterThan(50);

    foreach (['ar', 'en'] as $locale) {
        $strings = require lang_path("{$locale}/dashboard.php");
        $missing = array_values(array_filter($keys, fn (string $key) => ! is_string(Arr::get($strings, $key))));
        expect($missing)->toBe([], "lang/{$locale}/dashboard.php misses: ".implode(', ', $missing));
    }
});

it('has the same keys in both languages', function () {
    $ar = array_keys(Arr::dot(require lang_path('ar/dashboard.php')));
    $en = array_keys(Arr::dot(require lang_path('en/dashboard.php')));
    sort($ar);
    sort($en);

    expect($ar)->toBe($en);
});
