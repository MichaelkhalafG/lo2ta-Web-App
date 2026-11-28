<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Models\BuyerBudget;
use App\Models\Unit;
use App\Support\Matching\Matcher;
use App\Support\Table\TableQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer budgets and matching (PLAN 2.4; part 7.4; G9, G10; both roles). Budgets are listed for the team; matching
 * takes a unit and lists the buyers whose budget fits, or a buyer and lists the units that fit, best first, with the
 * reasons. Opening a deal from a pair comes with part 7.5.
 */
class MatchingController extends Controller
{
    public function budgets(Request $request): Response
    {
        $table = TableQuery::for($request)
            ->sortable(['updated_at', 'cash_available', 'max_installment'], default: 'updated_at', direction: 'desc', tiebreaker: 'id')
            ->perPage(default: 25, max: 100);
        $query = BuyerBudget::query()->with(['user', 'areas', 'compounds']);
        $search = $table->resolvedSearch();
        if ($search !== null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
        }
        $locale = app()->getLocale();
        $map = static function (object $b) use ($locale): array {
            if (! $b instanceof BuyerBudget) {
                return [];
            }

            return [...PeopleController::budget($b, $locale), 'name' => $b->user?->name, 'suspended' => $b->user?->isSuspended() ?? false];
        };

        return Inertia::render('Budgets/Index', ['table' => $table->paginate($query, $map)]);
    }

    public function index(Request $request, Matcher $matcher): Response
    {
        $locale = app()->getLocale();
        $unitId = $request->integer('unit');
        $buyerId = $request->integer('buyer');

        if ($unitId > 0) {
            $unit = Unit::query()->whereKey($unitId)->whereIn('state', UnitState::publicValues())->with(['compound', 'area'])->firstOrFail();
            $rows = $matcher->buyersFor($unit)->map(static fn (array $row): array => [
                'id' => $row['budget']->user_id,
                'title' => $row['budget']->user->name ?? '#'.$row['budget']->user_id,
                'subtitle' => $row['budget']->user?->email,
                'score' => $row['assessment']['score'],
                'ranked' => $row['assessment']['ranked'],
                'facts' => [
                    'cash' => number_format($row['budget']->cash_available),
                    'monthly' => number_format($row['assessment']['buyer_monthly']),
                ],
            ])->values()->all();

            return Inertia::render('Matching/Index', [
                'mode' => 'unit',
                'subject' => [
                    'id' => $unit->id,
                    'title' => self::unitTitle($unit, $locale),
                    'cash_needed' => number_format($matcher->cashNeeded($unit)),
                    'monthly' => number_format(Matcher::monthly((int) $unit->paid_amount >= (int) $unit->total_price ? 0 : $unit->installment_amount, $unit->installment_frequency)),
                ],
                'rows' => $rows,
                'pick' => null,
            ]);
        }

        if ($buyerId > 0) {
            $budget = BuyerBudget::query()->where('user_id', $buyerId)->with(['user', 'areas', 'compounds'])->firstOrFail();
            $rows = $matcher->unitsFor($budget)->map(static fn (array $row): array => [
                'id' => $row['unit']->id,
                'title' => self::unitTitle($row['unit'], $locale),
                'subtitle' => $row['unit']->area?->nameFor($locale),
                'score' => $row['assessment']['score'],
                'ranked' => $row['assessment']['ranked'],
                'facts' => [
                    'cash' => number_format($row['assessment']['cash_needed']),
                    'monthly' => number_format($row['assessment']['monthly']),
                ],
            ])->values()->all();

            return Inertia::render('Matching/Index', [
                'mode' => 'buyer',
                'subject' => [
                    'id' => $budget->user_id,
                    'title' => $budget->user->name ?? '#'.$budget->user_id,
                    'cash_needed' => number_format($budget->cash_available),
                    'monthly' => number_format(Matcher::monthly($budget->max_installment, $budget->installment_frequency)),
                ],
                'rows' => $rows,
                'pick' => null,
            ]);
        }

        return Inertia::render('Matching/Index', [
            'mode' => null,
            'subject' => null,
            'rows' => [],
            'pick' => [
                'units' => Unit::query()->public()->with('compound')->latest('published_at')->limit(30)->get()
                    ->map(static fn (Unit $u): array => ['id' => $u->id, 'label' => self::unitTitle($u, $locale)])->values()->all(),
                'buyers' => BuyerBudget::query()->with('user')->latest('updated_at')->limit(30)->get()
                    ->map(static fn (BuyerBudget $b): array => ['id' => $b->user_id, 'label' => ($b->user->name ?? '#'.$b->user_id).' · '.number_format($b->cash_available)])->values()->all(),
            ],
        ]);
    }

    private static function unitTitle(Unit $unit, string $locale): string
    {
        return trim(($unit->code ?? '').' · '.($unit->sub_type?->label() ?? '').' · '.($unit->compound?->nameFor($locale) ?? ''), ' ·');
    }
}
