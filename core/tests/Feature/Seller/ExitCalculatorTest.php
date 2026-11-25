<?php

use App\Support\Calculator\ExitCalculator;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;

/*
| PLAN 4.2 with the values the client confirmed on 2026-11-25: cancellation 15% of the total price (one value), the
| refund in one payment, the transfer fee a fixed EGP 50,000 unless the seller gives their own (a share or an
| amount), a late-payment penalty of 1% of the overdue installments on both exits (Michael 2026-11-25), the 50%
| deep-loss line, 0% Lokta commission for the seller.
*/

function calc(array $overrides = []): array
{
    return app(ExitCalculator::class)->compute([
        'total_price' => 6_000_000, 'paid_amount' => 1_500_000, 'years_paid' => 3, 'has_overdue' => false,
        'overdue_amount' => 0, 'overdue_settlement' => null, 'accepts_less' => false, 'lowest_accepted_amount' => null,
        'unit_transfer_fee_bp' => null, ...$overrides,
    ]);
}

it('computes the cancellation on the total price, refunded in one payment, and the zone', function () {
    $r = calc();

    // 15% of 6,000,000 = 900,000 kept; 1,500,000 - 900,000 = 600,000 back at once; a 60% loss is past the 50% line.
    expect($r['cancel'])->toMatchArray([
        'penalty_min' => 900_000, 'penalty_max' => 900_000, 'refund_min' => 600_000, 'refund_max' => 600_000,
        'refund_rule' => 'lump', 'refund_years' => 0, 'zone' => 'deep_loss', 'refund_pv_min' => null, 'late_fees' => 0,
    ])->and(calc(['paid_amount' => 800_000])['cancel']['zone'])->toBe('wipeout')
        ->and(calc(['paid_amount' => 5_000_000])['cancel']['zone'])->toBe('moderate_loss');
});

it('keeps the other refund rules working: the years paid, or a fixed number of years', function () {
    app(Settings::class)->set('refund_rule', 'mirror');
    expect(calc()['cancel']['refund_years'])->toBe(3);

    app(Settings::class)->set('refund_rule', 'fixed');
    app(Settings::class)->set('refund_years_fixed', 5);
    expect(calc()['cancel']['refund_years'])->toBe(5);
});

it('uses the fixed EGP 50,000 fee by default, and the seller\'s own share or amount instead', function () {
    $default = calc();
    expect($default['transfer'])->toMatchArray(['transfer_fee' => 50_000, 'transfer_fee_bp' => null, 'transfer_fee_source' => 'setting', 'net' => 1_450_000])
        ->and($default['advantage_min'])->toBe(1_450_000 - 600_000);

    $share = calc(['unit_transfer_fee_bp' => 250]);
    expect($share['transfer'])->toMatchArray(['transfer_fee' => 150_000, 'transfer_fee_bp' => 250, 'transfer_fee_source' => 'unit']);

    $amount = calc(['unit_transfer_fee_fixed' => 35_000]);
    expect($amount['transfer'])->toMatchArray(['transfer_fee' => 35_000, 'transfer_fee_bp' => null, 'transfer_fee_source' => 'unit', 'net' => 1_465_000]);
});

it('charges 1% of the overdue installments once, on both exits', function () {
    // Deducted from the proceeds: the arrears and their penalty. Cancelling: the penalty lowers the refund.
    $r = calc(['unit_transfer_fee_bp' => 250, 'accepts_less' => true, 'lowest_accepted_amount' => 1_400_000,
        'has_overdue' => true, 'overdue_amount' => 50_000, 'overdue_settlement' => 'deduct']);

    // 1,500,000 - 100,000 given up = 1,400,000 received; - 150,000 fee - 50,000 arrears - 500 penalty.
    expect($r['transfer'])->toMatchArray(['receive' => 1_400_000, 'transfer_fee' => 150_000, 'transfer_fee_source' => 'unit',
        'arrears_to_developer' => 50_000, 'late_fees' => 500, 'lokta_fee' => 0, 'net' => 1_199_500])
        ->and($r['cancel']['late_fees'])->toBe(500)
        ->and($r['cancel']['refund_min'])->toBe(599_500)
        ->and($r['advantage_max'])->toBe(1_199_500 - $r['cancel']['refund_min']);

    // Settled by the seller first: nothing is deducted, the seller settles the arrears and the penalty.
    $self = calc(['has_overdue' => true, 'overdue_amount' => 50_000, 'overdue_settlement' => 'self']);
    expect($self['transfer'])->toMatchArray(['arrears_to_developer' => 0, 'late_fees' => 0, 'arrears_self' => 50_500, 'net' => 1_450_000]);
});

it('marks nothing provisional with the confirmed values, and only an unconfirmed rate the figures use', function () {
    // The 50% line is a display threshold and the discount rate is unused while the present-value line is off.
    expect(calc()['provisional'])->toBe([]);

    app(Settings::class)->set('show_present_value', true);
    $pv = calc(['paid_amount' => 3_000_000]);
    expect($pv['provisional'])->toBe(['discount_rate_bp'])
        ->and($pv['cancel']['refund_pv_min'])->toBe($pv['cancel']['refund_min']);

    app(Settings::class)->set('refund_rule', 'mirror');
    expect(calc(['paid_amount' => 3_000_000])['cancel']['refund_pv_min'])->toBeLessThan(2_100_000)->toBeGreaterThan(0);

    app(Settings::class)->set('penalty_max_bp', 2000, confirmed: false);
    expect(calc()['provisional'])->toContain('penalty_max_bp');
});

it('counts the years paid from the contract month, at least one', function () {
    $today = CarbonImmutable::create(2026, 11, 18);

    expect(ExitCalculator::yearsPaid(2023, 5, $today))->toBe(4)
        ->and(ExitCalculator::yearsPaid(2026, 10, $today))->toBe(1)
        ->and(ExitCalculator::yearsPaid(null, null, $today))->toBe(1);
});
