<?php

namespace App\Support\Matching;

use App\Enums\DeliveryPreference;
use App\Enums\DeliveryStatus;
use App\Enums\InstallmentFrequency;
use App\Models\Area;
use App\Models\BuyerBudget;
use App\Models\Compound;
use App\Models\Unit;
use App\Support\Listing\UnitFacts;
use App\Support\Settings\Settings;
use Illuminate\Support\Collection;

/**
 * Matching buyers and units (PLAN 2.4; G9, G10, Michael 2026-11-27). Must fit: cash (the amount to the owner, the
 * lowest accepted amount when the seller opted in, plus the 1% due on completion), installment (both as a monthly
 * figure), type and area (an area includes its districts, a governorate its areas). Rank: compound, bedrooms,
 * delivery and target price. Nothing here is shown to a buyer; the team uses it to open a deal (part 7.5).
 */
final class Matcher
{
    /** @var array<int, list<int>>|null area id => itself and every ancestor */
    private ?array $ancestors = null;

    public function __construct(private readonly Settings $settings) {}

    /** G9: what the buyer pays now. */
    public function cashNeeded(Unit $unit): int
    {
        $owner = $unit->accepts_less && $unit->lowest_accepted_amount !== null ? $unit->lowest_accepted_amount : (int) $unit->paid_amount;

        return $owner + UnitFacts::buyerFee($unit, $this->settings)['amount'];
    }

    /** An installment as a monthly figure (PLAN 3.5); a fully paid contract owes none. */
    public static function monthly(?int $amount, ?InstallmentFrequency $frequency): int
    {
        if ($amount === null || $amount <= 0 || $frequency === null) {
            return 0;
        }

        return intdiv($amount * $frequency->perYear() + 6, 12);
    }

    /**
     * @return array{fits: bool, score: int, failed: list<string>, ranked: list<string>, cash_needed: int, monthly: int, buyer_monthly: int}
     */
    public function assess(BuyerBudget $budget, Unit $unit): array
    {
        $failed = [];
        $cash = $this->cashNeeded($unit);
        if ($budget->cash_available < $cash) {
            $failed[] = 'cash';
        }
        $monthly = (int) $unit->paid_amount >= (int) $unit->total_price ? 0 : self::monthly($unit->installment_amount, $unit->installment_frequency);
        $buyerMonthly = self::monthly($budget->max_installment, $budget->installment_frequency);
        if ($monthly > $buyerMonthly) {
            $failed[] = 'installment';
        }
        $types = $budget->main_types;
        if ($types !== [] && ($unit->main_type === null || ! in_array($unit->main_type->value, $types, true))) {
            $failed[] = 'type';
        }
        $areas = $budget->areas->map(static fn (Area $a): int => $a->id)->all();
        if ($areas !== [] && ($unit->area_id === null || array_intersect($this->lineage($unit->area_id), $areas) === [])) {
            $failed[] = 'area';
        }

        $ranked = [];
        $score = 0;
        $compounds = $budget->compounds->map(static fn (Compound $c): int => $c->id)->all();
        if ($compounds !== [] && $unit->compound_id !== null && in_array($unit->compound_id, $compounds, true)) {
            $score += 3;
            $ranked[] = 'compound';
        }
        if ($budget->min_bedrooms !== null && $unit->bedrooms !== null && $unit->bedrooms >= $budget->min_bedrooms) {
            $score += 2;
            $ranked[] = 'bedrooms';
        }
        $delivery = $budget->delivery_pref;
        if (($delivery === DeliveryPreference::Ready && $unit->delivery_status === DeliveryStatus::Ready)
            || ($delivery === DeliveryPreference::UnderConstruction && $unit->delivery_status === DeliveryStatus::UnderConstruction)) {
            $score += 2;
            $ranked[] = 'delivery';
        }
        if ($budget->target_price !== null && $unit->total_price !== null && $unit->total_price <= $budget->target_price) {
            $score += 2;
            $ranked[] = 'target';
        }

        return ['fits' => $failed === [], 'score' => $score, 'failed' => $failed, 'ranked' => $ranked, 'cash_needed' => $cash, 'monthly' => $monthly, 'buyer_monthly' => $buyerMonthly];
    }

    /**
     * Buyers whose budget fits the unit, best first (never the seller themself, never a suspended account).
     *
     * @return Collection<int, array{budget: BuyerBudget, assessment: array{fits: true, score: int, failed: list<string>, ranked: list<string>, cash_needed: int, monthly: int, buyer_monthly: int}}>
     */
    public function buyersFor(Unit $unit, int $limit = 50): Collection
    {
        return BuyerBudget::query()->with(['user', 'areas', 'compounds'])
            ->where('user_id', '!=', $unit->seller_id)
            ->whereHas('user', fn ($q) => $q->whereNull('suspended_at'))
            ->get()
            ->map(fn (BuyerBudget $b): array => ['budget' => $b, 'assessment' => $this->assess($b, $unit)])
            ->filter(fn (array $row): bool => $row['assessment']['fits'])
            ->sortByDesc(fn (array $row): string => sprintf('%03d-%s', $row['assessment']['score'], $row['budget']->updated_at?->format('YmdHis') ?? ''))
            ->take($limit)->values();
    }

    /**
     * Units on the site that fit the buyer's budget, best first.
     *
     * @return Collection<int, array{unit: Unit, assessment: array{fits: true, score: int, failed: list<string>, ranked: list<string>, cash_needed: int, monthly: int, buyer_monthly: int}}>
     */
    public function unitsFor(BuyerBudget $budget, int $limit = 50): Collection
    {
        return Unit::query()->public()->with(['compound', 'area'])->where('seller_id', '!=', $budget->user_id)->get()
            ->map(fn (Unit $u): array => ['unit' => $u, 'assessment' => $this->assess($budget, $u)])
            ->filter(fn (array $row): bool => $row['assessment']['fits'])
            ->sortByDesc(fn (array $row): string => sprintf('%03d-%s', $row['assessment']['score'], $row['unit']->published_at?->format('YmdHis') ?? ''))
            ->take($limit)->values();
    }

    /**
     * @return list<int> the area and every area above it
     */
    private function lineage(int $areaId): array
    {
        if ($this->ancestors === null) {
            $parents = Area::query()->pluck('parent_id', 'id')->all();
            $this->ancestors = [];
            foreach (array_keys($parents) as $id) {
                $line = [];
                $current = $id;
                for ($i = 0; $i < 6 && $current !== null; $i++) {
                    $line[] = (int) $current;
                    $next = $parents[$current] ?? null;
                    $current = is_numeric($next) ? (int) $next : null;
                }
                $this->ancestors[(int) $id] = $line;
            }
        }

        return $this->ancestors[$areaId] ?? [$areaId];
    }
}
