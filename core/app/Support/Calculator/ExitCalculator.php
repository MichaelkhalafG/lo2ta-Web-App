<?php

namespace App\Support\Calculator;

use App\Enums\OverdueSettlement;
use App\Models\Unit;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;

/**
 * The seller's exit calculation (PLAN 4.2, decided 2026-11-17): cancelling with the developer against transferring
 * through Lokta. Every constant is a setting; most are provisional until the market research (PLAN 4.3), and the
 * answer says which, so the wizard, the exit report and the PDF can mark the figures as provisional.
 * Developer policies (PLAN 3.7) plug in here in phase 6; until then the unit's own transfer fee, then the settings.
 * Money is whole EGP.
 */
final class ExitCalculator
{
    /** Settings the calculation reads; each is listed in the snapshot kept with an exit report. */
    public const SETTINGS = [
        'penalty_basis', 'penalty_min_bp', 'penalty_max_bp', 'late_fee_bp', 'refund_rule', 'refund_years_fixed',
        'deep_loss_ratio_bp', 'show_present_value', 'discount_rate_bp', 'transfer_fee_payer', 'transfer_fee_mode',
        'transfer_fee_bp', 'transfer_fee_fixed',
    ];

    public function __construct(private readonly Settings $settings) {}

    /**
     * The calculation from a unit's declared figures, or null while step 3 lacks what it needs.
     *
     * @return array<string, mixed>|null
     */
    public function forUnit(Unit $unit, ?CarbonImmutable $today = null): ?array
    {
        if ($unit->total_price === null || $unit->paid_amount === null || $unit->paid_amount > $unit->total_price) {
            return null;
        }

        return $this->compute([
            'total_price' => $unit->total_price,
            'paid_amount' => $unit->paid_amount,
            'years_paid' => self::yearsPaid($unit->contract_year, $unit->contract_month, $today ?? CarbonImmutable::today()),
            'has_overdue' => $unit->has_overdue,
            'overdue_amount' => $unit->overdue_amount ?? 0,
            'overdue_settlement' => $unit->overdue_settlement?->value,
            'accepts_less' => $unit->accepts_less,
            'lowest_accepted_amount' => $unit->lowest_accepted_amount,
            'unit_transfer_fee_bp' => $unit->transfer_fee_bp,
        ]);
    }

    /**
     * @param  array{total_price: int, paid_amount: int, years_paid: int, has_overdue: bool, overdue_amount: int, overdue_settlement: string|null, accepts_less: bool, lowest_accepted_amount: int|null, unit_transfer_fee_bp: int|null}  $in
     * @return array<string, mixed>
     */
    public function compute(array $in): array
    {
        $s = $this->settings;
        $total = $in['total_price'];
        $paid = $in['paid_amount'];

        // Cancellation with the developer.
        $basis = $s->string('penalty_basis') === 'paid' ? $paid : $total;
        $penaltyMin = self::part($basis, $s->int('penalty_min_bp'));
        $penaltyMax = self::part($basis, $s->int('penalty_max_bp'));
        $lateFees = $in['has_overdue'] ? self::part($in['overdue_amount'], $s->int('late_fee_bp')) : 0;
        $refundMin = max(0, $paid - $penaltyMax - $lateFees);
        $refundMax = max(0, $paid - $penaltyMin - $lateFees);
        $refundYears = $s->string('refund_rule') === 'fixed' ? $s->int('refund_years_fixed') : $in['years_paid'];
        $lossWorst = $paid > 0 ? ($paid - $refundMin) / $paid : 0.0;
        $zone = $refundMin <= 0 ? 'wipeout' : ($lossWorst > $s->int('deep_loss_ratio_bp') / 10_000 ? 'deep_loss' : 'moderate_loss');
        $pv = $s->bool('show_present_value') ? self::presentValue($refundMin, $refundYears, $s->int('discount_rate_bp')) : null;

        // Transfer through Lokta: 0% commission for the seller (PLAN 4.2).
        if ($in['unit_transfer_fee_bp'] !== null) {
            $feeSource = 'unit';
            $feeBp = $in['unit_transfer_fee_bp'];
            $transferFee = self::part($total, $feeBp);
        } elseif ($s->string('transfer_fee_mode') === 'fixed') {
            $feeSource = 'setting';
            $feeBp = null;
            $transferFee = $s->int('transfer_fee_fixed');
        } else {
            $feeSource = 'setting';
            $feeBp = $s->int('transfer_fee_bp');
            $transferFee = self::part($total, $feeBp);
        }
        $sellerPaysFee = $s->string('transfer_fee_payer') === 'seller';
        $givenUp = $in['accepts_less'] && $in['lowest_accepted_amount'] !== null ? max(0, $paid - $in['lowest_accepted_amount']) : 0;
        $arrears = $in['has_overdue'] && $in['overdue_settlement'] === OverdueSettlement::Deducted->value ? $in['overdue_amount'] : 0;
        $receive = $paid - $givenUp;
        $net = $receive - ($sellerPaysFee ? $transferFee : 0) - $arrears;

        $provisional = [];
        foreach ($this->settings->all() as $key => $entry) {
            if (in_array($key, self::SETTINGS, true) && $entry['provisional']) {
                // The unit's own transfer fee replaces the provisional default.
                if ($feeSource === 'unit' && in_array($key, ['transfer_fee_mode', 'transfer_fee_bp', 'transfer_fee_fixed'], true)) {
                    continue;
                }
                $provisional[] = $key;
            }
        }

        return [
            'inputs' => $in,
            'cancel' => [
                'penalty_min' => $penaltyMin,
                'penalty_max' => $penaltyMax,
                'penalty_basis' => $s->string('penalty_basis'),
                'late_fees' => $lateFees,
                'refund_min' => $refundMin,
                'refund_max' => $refundMax,
                'refund_years' => $refundYears,
                'refund_pv_min' => $pv,
                'zone' => $zone,
            ],
            'transfer' => [
                'receive' => $receive,
                'given_up' => $givenUp,
                'transfer_fee' => $transferFee,
                'transfer_fee_bp' => $feeBp,
                'transfer_fee_source' => $feeSource,
                'transfer_fee_payer' => $sellerPaysFee ? 'seller' : 'buyer',
                'arrears_to_developer' => $arrears,
                'arrears_settlement' => $in['has_overdue'] ? $in['overdue_settlement'] : null,
                'arrears_self' => $in['has_overdue'] && $in['overdue_settlement'] === OverdueSettlement::SellerPaysFirst->value ? $in['overdue_amount'] : 0,
                'lokta_fee' => 0,
                'net' => $net,
            ],
            'advantage_min' => $net - $refundMax,
            'advantage_max' => $net - $refundMin,
            'provisional' => $provisional,
        ];
    }

    /**
     * The settings as they were, kept with each exit report (PLAN 3.7 #16), so a later change never rewrites one.
     *
     * @return array<string, int|bool|string>
     */
    public function snapshot(): array
    {
        $out = [];
        foreach (self::SETTINGS as $key) {
            $out[$key] = $this->settings->get($key);
        }

        return $out;
    }

    /** Whole years from the contract month to today, at least 1 (the refund "mirrors" them, PLAN 4.2). */
    public static function yearsPaid(?int $year, ?int $month, CarbonImmutable $today): int
    {
        if ($year === null) {
            return 1;
        }
        $months = ($today->year - $year) * 12 + ($today->month - ($month ?? 1));

        return max(1, (int) ceil($months / 12));
    }

    private static function part(int $amount, int $bp): int
    {
        return intdiv($amount * $bp + 5_000, 10_000);
    }

    /** The refund paid in equal yearly parts over $years, discounted at $rateBp a year. */
    private static function presentValue(int $amount, int $years, int $rateBp): int
    {
        if ($years <= 0 || $rateBp <= 0) {
            return $amount;
        }
        $yearly = $amount / $years;
        $rate = $rateBp / 10_000;
        $sum = 0.0;
        for ($k = 1; $k <= $years; $k++) {
            $sum += $yearly / ((1 + $rate) ** $k);
        }

        return (int) round($sum);
    }
}
