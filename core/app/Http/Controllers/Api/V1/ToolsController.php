<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReportIndicator;
use App\Support\Accounts\ClientIp;
use App\Support\Cache\VersionedCache;
use App\Support\Calculator\ExitCalculator;
use App\Support\Calculator\MarketValues;
use App\Support\Seller\Throttle;
use App\Support\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The public tools (PLAN 1.2, 4.1; Phase 6): the exit calculator's two tabs, the market values the calculator and
 * the reports page show, and the client's indicators. The calculator stores nothing and needs no sign-in (J12);
 * each calculation counts against an hourly limit per address (rl_calculator_ip, Michael 2026-11-25).
 * Nothing here carries a source, a note or a developer name (J10; K6, Michael 2026-11-26: the public calculator
 * uses the market values only, developer policies stay with the seller wizard and the dashboard).
 */
class ToolsController extends Controller
{
    private const MONEY_MAX = 1_000_000_000;

    public function marketValues(MarketValues $values): JsonResponse
    {
        return response()->json(['data' => ['values' => $values->publicList()]]);
    }

    public function indicators(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json(['data' => VersionedCache::remember('tools', "indicators:{$locale}", static function () use ($locale): array {
            return ReportIndicator::query()->where('is_published', true)->orderBy('sort')->orderBy('id')->get()
                ->map(static fn (ReportIndicator $i): array => [
                    'title' => $locale === 'en' ? $i->title_en : $i->title_ar,
                    'value' => $i->value,
                    'unit' => $i->unit,
                    'note' => $locale === 'en' ? $i->note_en : $i->note_ar,
                    'as_of' => $i->as_of->format('Y-m'),
                ])->values()->all();
        })]);
    }

    /** Seller tab (PLAN 4.1): cancelling with the developer against transferring through Lokta. */
    public function seller(Request $request, ExitCalculator $calculator, Throttle $throttle): JsonResponse
    {
        $throttle->hit('calculator', ['rl_calculator_ip' => 'ip:'.ClientIp::of($request)]);
        $request->validate([
            'total_price' => ['required', 'integer', 'min:1', 'max:'.self::MONEY_MAX],
            'paid_amount' => ['required', 'integer', 'min:1', 'max:'.self::MONEY_MAX],
            'years_paid' => ['required', 'integer', 'min:1', 'max:30'],
            'overdue_amount' => ['nullable', 'integer', 'min:0', 'max:'.self::MONEY_MAX],
        ]);
        $total = $request->integer('total_price');
        $paid = $request->integer('paid_amount');
        $overdue = $request->integer('overdue_amount');
        if ($paid > $total) {
            throw ValidationException::withMessages(['paid_amount' => self::text('tools.paid_over_total')]);
        }
        if ($overdue > $total - $paid) {
            throw ValidationException::withMessages(['overdue_amount' => self::text('tools.overdue_over_remaining')]);
        }

        // Market values only (K6): a developer field, if sent, is ignored like any other unknown field.
        $result = $calculator->compute([
            'total_price' => $total,
            'paid_amount' => $paid,
            'years_paid' => $request->integer('years_paid'),
            'has_overdue' => $overdue > 0,
            'overdue_amount' => $overdue,
            // The public calculator has no settlement choice: overdue installments are paid from the proceeds.
            'overdue_settlement' => $overdue > 0 ? 'deduct' : null,
            'accepts_less' => false,
            'lowest_accepted_amount' => null,
            'unit_transfer_fee_bp' => null,
        ]);
        unset($result['inputs'], $result['policy']);

        return response()->json(['data' => $result]);
    }

    /** Buyer tab (PLAN 4.1): the day-one difference; no transfer fee in the buyer's figures (decided 2026-11-17). */
    public function buyer(Request $request, Settings $settings, Throttle $throttle): JsonResponse
    {
        $throttle->hit('calculator', ['rl_calculator_ip' => 'ip:'.ClientIp::of($request)]);
        $request->validate([
            'contract_price' => ['required', 'integer', 'min:1', 'max:'.self::MONEY_MAX],
            'price_today' => ['required', 'integer', 'min:1', 'max:'.self::MONEY_MAX],
        ]);
        $contract = $request->integer('contract_price');
        $today = $request->integer('price_today');
        $feeBp = $settings->int('buyer_fee_bp');
        $fee = intdiv($contract * $feeBp + 5_000, 10_000);
        $vat = intdiv($fee * $settings->int('vat_bp') + 5_000, 10_000);
        $gain = $today - $contract - $fee - $vat;

        return response()->json(['data' => [
            'contract_price' => $contract,
            'price_today' => $today,
            'fee_bp' => $feeBp,
            'fee' => $fee,
            'fee_vat' => $vat,
            'gain' => $gain,
            'gain_bp' => intdiv($gain * 10_000, $today),
        ]]);
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
