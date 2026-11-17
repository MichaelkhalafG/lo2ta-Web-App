<?php

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\Settings\Settings;

beforeEach(fn () => app(Settings::class)->seedDefaults());

it('answers the decided fee values', function () {
    $s = app(Settings::class);

    expect($s->int('buyer_fee_bp'))->toBe(100)
        ->and($s->int('vat_bp'))->toBe(0)
        ->and($s->string('transfer_fee_payer'))->toBe('seller')
        ->and($s->bool('show_present_value'))->toBeFalse()
        ->and($s->bool('receipts_required'))->toBeFalse()
        ->and($s->int('id_retention_days'))->toBe(90)
        ->and($s->int('draft_id_retention_days'))->toBe(180);
});

it('marks unresearched calculator values as provisional and decided ones as not', function () {
    $all = app(Settings::class)->all();

    expect($all['penalty_min_bp']['provisional'])->toBeTrue()
        ->and($all['transfer_fee_bp']['provisional'])->toBeTrue()
        ->and($all['buyer_fee_bp']['provisional'])->toBeFalse()
        ->and($all['transfer_fee_payer']['provisional'])->toBeFalse();
});

it('validates a write against the definition', function () {
    $s = app(Settings::class);

    expect(fn () => $s->set('buyer_fee_bp', 'a lot'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $s->set('buyer_fee_bp', 5000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $s->set('transfer_fee_payer', 'developer'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $s->set('no_such_key', 1))->toThrow(InvalidArgumentException::class);
});

it('stores, clears the provisional flag, refreshes the cache and logs the change', function () {
    $s = app(Settings::class);
    expect($s->int('penalty_min_bp'))->toBe(1000);

    $s->set('penalty_min_bp', '1200');

    expect($s->int('penalty_min_bp'))->toBe(1200)
        ->and($s->all()['penalty_min_bp']['provisional'])->toBeFalse();

    $line = ActivityLog::query()->where('action', 'settings.updated')->latest('id')->firstOrFail();
    expect($line->before)->toMatchArray(['value' => '1000'])
        ->and($line->after)->toMatchArray(['value' => '1200']);
});

it('never overwrites a stored value when the defaults are seeded again', function () {
    app(Settings::class)->set('installment_check_tolerance_bp', 1500);

    expect(app(Settings::class)->seedDefaults())->toBe(0)
        ->and(app(Settings::class)->int('installment_check_tolerance_bp'))->toBe(1500)
        ->and(Setting::query()->count())->toBe(count(config('settings')));
});
