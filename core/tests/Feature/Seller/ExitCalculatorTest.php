<?php

use App\Support\Calculator\ExitCalculator;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;

/*
| PLAN 4.2 with the provisional defaults (penalty 10-15% of the total, refund mirrors the years paid, 50% deep-loss
| line, transfer fee from the unit or the settings, 0% Lokta commission for the seller).
*/

function calc(array $overrides = []): array
{
    return app(ExitCalculator::class)->compute([
        'total_price' => 6_000_000, 'paid_amount' => 1_500_000, 'years_paid' => 3, 'has_overdue' => false,
        'overdue_amount' => 0, 'overdue_settlement' => null, 'accepts_less' => false, 'lowest_accepted_amount' => null,
        'unit_transfer_fee_bp' => null, ...$overrides,
    ]);
}

it('computes the cancellation range on the total price and the zone', function () {
    $r = calc();

    expect($r['cancel'])->toMatchArray([
        'penalty_min' => 600_000, 'penalty_max' => 900_000, 'refund_min' => 600_000, 'refund_max' => 900_000,
        'refund_years' => 3, 'zone' => 'deep_loss', 'refund_pv_min' => null,
    ])->and(calc(['paid_amount' => 800_000])['cancel']['zone'])->toBe('wipeout')
        ->and(calc(['paid_amount' => 5_000_000])['cancel']['zone'])->toBe('moderate_loss');
});

it('computes the transfer net: what was paid, less the fee, what is given up and deducted arrears', function () {
    $r = calc(['unit_transfer_fee_bp' => 250, 'accepts_less' => true, 'lowest_accepted_amount' => 1_400_000,
        'has_overdue' => true, 'overdue_amount' => 50_000, 'overdue_settlement' => 'deduct']);

    // 1,500,000 - 100,000 given up = 1,400,000 received; - 150,000 fee (2.5% of 6,000,000) - 50,000 arrears.
    expect($r['transfer'])->toMatchArray(['receive' => 1_400_000, 'transfer_fee' => 150_000, 'transfer_fee_source' => 'unit',
        'arrears_to_developer' => 50_000, 'lokta_fee' => 0, 'net' => 1_200_000])
        ->and($r['advantage_max'])->toBe(1_200_000 - $r['cancel']['refund_min'])
        ->and($r['provisional'])->not->toContain('transfer_fee_bp');

    $self = calc(['has_overdue' => true, 'overdue_amount' => 50_000, 'overdue_settlement' => 'self']);
    expect($self['transfer']['arrears_to_developer'])->toBe(0)->and($self['transfer']['arrears_self'])->toBe(50_000);
});

it('uses the settings, says which are provisional, and shows the present value only when switched on', function () {
    app(Settings::class)->set('transfer_fee_bp', 100);
    app(Settings::class)->set('show_present_value', true);
    app(Settings::class)->set('discount_rate_bp', 2000);

    $r = calc();

    expect($r['transfer']['transfer_fee'])->toBe(60_000)
        ->and($r['cancel']['refund_pv_min'])->toBeLessThan(600_000)->toBeGreaterThan(0)
        ->and($r['provisional'])->toContain('penalty_min_bp')->not->toContain('transfer_fee_bp');
});

it('counts the years paid from the contract month, at least one', function () {
    $today = CarbonImmutable::create(2026, 11, 18);

    expect(ExitCalculator::yearsPaid(2023, 5, $today))->toBe(4)
        ->and(ExitCalculator::yearsPaid(2026, 10, $today))->toBe(1)
        ->and(ExitCalculator::yearsPaid(null, null, $today))->toBe(1);
});
