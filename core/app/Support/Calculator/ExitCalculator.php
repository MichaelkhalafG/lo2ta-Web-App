<?php

namespace App\Support\Calculator;

use App\Enums\OverdueSettlement;
use App\Models\DeveloperPolicy;
use App\Models\Unit;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;

/**
 * The seller's exit calculation (PLAN 4.2, decided 2026-11-17): cancelling with the developer against transferring
 * through Lokta. Every constant is a setting; most are provisional until the market research (PLAN 4.3), and the
 * answer says which, so the wizard, the exit report and the PDF can mark the figures as provisional.
 * A developer's own terms (developer_policies, approved by the client: J3) replace the market values for that
 * developer, value by value; the unit's own transfer fee replaces both. Money is whole EGP.
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
        $policy = $unit->developer_id !== null
            ? DeveloperPolicy::query()->approved()->with('developer')->where('developer_id', $unit->developer_id)->first()
            : null;

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
            'unit_transfer_fee_fixed' => $unit->transfer_fee_fixed,
        ], $policy);
    }

    /**
     * The late-payment penalty (client 2026-11-25) is 1% of each late installment, charged once: the same as one
     * share of the overdue amount. It is owed to the developer on both exits (Michael 2026-11-25): it lowers the
     * refund on a cancellation, and on a transfer it is settled with the arrears. The 50% deep-loss line is a display
     * threshold and the discount rate is unused while the present-value line is off, so neither marks the figures
     * provisional (Michael 2026-11-25).
     *
     * @param  array{total_price: int, paid_amount: int, years_paid: int, has_overdue: bool, overdue_amount: int, overdue_settlement: string|null, accepts_less: bool, lowest_accepted_amount: int|null, unit_transfer_fee_bp: int|null, unit_transfer_fee_fixed?: int|null}  $in
     * @return array<string, mixed>
     */
    public function compute(array $in, ?DeveloperPolicy $policy = null): array
    {
        $v = $this->values($policy);
        $int = static fn (string $key): int => is_int($v[$key]) ? $v[$key] : 0;
        $total = $in['total_price'];
        $paid = $in['paid_amount'];

        // Cancellation with the developer.
        $basis = $v['penalty_basis'] === 'paid' ? $paid : $total;
        $penaltyMin = self::part($basis, $int('penalty_min_bp'));
        $penaltyMax = self::part($basis, max($int('penalty_min_bp'), $int('penalty_max_bp')));
        $lateFees = $in['has_overdue'] ? self::part($in['overdue_amount'], $int('late_fee_bp')) : 0;
        $refundMin = max(0, $paid - $penaltyMax - $lateFees);
        $refundMax = max(0, $paid - $penaltyMin - $lateFees);
        // One payment ('lump', client 2026-11-25) is 0 years; 'mirror' follows the years paid; 'fixed' a set number.
        $refundYears = match ($v['refund_rule']) {
            'lump' => 0,
            'fixed' => $int('refund_years_fixed'),
            default => $in['years_paid'],
        };
        $lossWorst = $paid > 0 ? ($paid - $refundMin) / $paid : 0.0;
        $zone = $refundMin <= 0 ? 'wipeout' : ($lossWorst > $int('deep_loss_ratio_bp') / 10_000 ? 'deep_loss' : 'moderate_loss');
        $pv = $v['show_present_value'] === true ? self::presentValue($refundMin, $refundYears, $int('discount_rate_bp')) : null;

        // Transfer through Lokta: 0% commission for the seller (PLAN 4.2). The seller's own figure (a share or an
        // amount, step 3) replaces the default.
        $unitFixed = $in['unit_transfer_fee_fixed'] ?? null;
        if ($in['unit_transfer_fee_bp'] !== null) {
            $feeSource = 'unit';
            $feeBp = $in['unit_transfer_fee_bp'];
            $transferFee = self::part($total, $feeBp);
        } elseif ($unitFixed !== null) {
            $feeSource = 'unit';
            $feeBp = null;
            $transferFee = $unitFixed;
        } elseif ($v['transfer_fee_mode'] === 'fixed') {
            $feeSource = 'setting';
            $feeBp = null;
            $transferFee = $int('transfer_fee_fixed');
        } else {
            $feeSource = 'setting';
            $feeBp = $int('transfer_fee_bp');
            $transferFee = self::part($total, $feeBp);
        }
        $sellerPaysFee = $v['transfer_fee_payer'] === 'seller';
        $givenUp = $in['accepts_less'] && $in['lowest_accepted_amount'] !== null ? max(0, $paid - $in['lowest_accepted_amount']) : 0;
        $deducted = $in['has_overdue'] && $in['overdue_settlement'] === OverdueSettlement::Deducted->value;
        $selfPaid = $in['has_overdue'] && $in['overdue_settlement'] === OverdueSettlement::SellerPaysFirst->value;
        $arrears = $deducted ? $in['overdue_amount'] : 0;
        $arrearsFees = $deducted ? $lateFees : 0;
        $receive = $paid - $givenUp;
        $net = $receive - ($sellerPaysFee ? $transferFee : 0) - $arrears - $arrearsFees;

        $overridden = $policy?->overrides() ?? [];
        $ignored = ['deep_loss_ratio_bp', ...($v['show_present_value'] === true ? [] : ['discount_rate_bp'])];
        $provisional = [];
        foreach ($this->settings->all() as $key => $entry) {
            if (in_array($key, self::SETTINGS, true) && ! in_array($key, $ignored, true) && $entry['provisional'] && ! array_key_exists($key, $overridden)) {
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
                'penalty_basis' => $v['penalty_basis'],
                'late_fees' => $lateFees,
                'refund_min' => $refundMin,
                'refund_max' => $refundMax,
                'refund_rule' => $v['refund_rule'],
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
                'late_fees' => $arrearsFees,
                'arrears_settlement' => $in['has_overdue'] ? $in['overdue_settlement'] : null,
                // Settled by the seller before the transfer: the arrears and their penalty.
                'arrears_self' => $selfPaid ? $in['overdue_amount'] + $lateFees : 0,
                'lokta_fee' => 0,
                'net' => $net,
            ],
            'advantage_min' => $net - $refundMax,
            'advantage_max' => $net - $refundMin,
            'provisional' => $provisional,
            // The values the figures used, and whose terms they are: the market's, or one developer's own.
            'settings' => $v,
            'policy' => $policy === null ? null : [
                'developer' => $policy->developer?->slug,
                'overrides' => array_keys($overridden),
                'checked_on' => $policy->checked_on->format('Y-m-d'),
            ],
        ];
    }

    /**
     * The values as they were, kept with each exit report (PLAN 3.7 #16), so a later change never rewrites one.
     *
     * @return array<string, int|bool|string>
     */
    public function snapshot(?DeveloperPolicy $policy = null): array
    {
        return $this->values($policy);
    }

    /**
     * The settings the calculation reads, with the policy's overrides on top.
     *
     * @return array<string, int|bool|string>
     */
    private function values(?DeveloperPolicy $policy): array
    {
        $out = [];
        foreach (self::SETTINGS as $key) {
            $out[$key] = $this->settings->get($key);
        }

        return [...$out, ...($policy?->overrides() ?? [])];
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
