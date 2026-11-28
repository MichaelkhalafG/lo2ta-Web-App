<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DealStage;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Deal;
use App\Models\DealStageEvent;
use App\Models\Unit;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Dashboard\Text;
use App\Support\Deals\DealFlow;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deals (PLAN 1.3, 2.4; part 7.5; both roles, can:work-clients): the list, one deal with its stage track as a revision
 * block, and the moves of G18. The commission is shown to an admin only (PLAN 2.4: the reviewer has no commissions).
 */
class DealsController extends Controller
{
    private const TZ = 'Africa/Cairo';

    public function index(Request $request): Response
    {
        $table = TableQuery::for($request)
            ->sortable(['opened_at', 'updated_at'], default: 'updated_at', direction: 'desc', tiebreaker: 'id')
            ->filterable(['stage' => DealStage::values(), 'open' => ['1', '0']])
            ->virtual(['open'])
            ->perPage(default: 25, max: 100);
        $query = Deal::query()->with(['unit', 'buyer', 'seller']);
        match ($table->resolvedFilters()['open'] ?? null) {
            '1' => $query->whereIn('stage', DealStage::openValues()),
            '0' => $query->whereNotIn('stage', DealStage::openValues()),
            default => null,
        };
        $map = static function (object $d): array {
            if (! $d instanceof Deal) {
                return [];
            }

            return [
                'id' => $d->id,
                'reference' => $d->reference(),
                'unit' => $d->unit?->code,
                'buyer' => $d->buyer?->name,
                'seller' => $d->seller?->name,
                'stage' => $d->stage->value,
                'stage_label' => $d->stage->label(),
                'opened_at' => $d->opened_at->timezone(self::TZ)->format('Y-m-d'),
                'updated_at' => $d->updated_at?->timezone(self::TZ)->format('Y-m-d H:i'),
            ];
        };

        return Inertia::render('Deals/Index', [
            'table' => $table->paginate($query, $map),
            'stages' => array_map(static fn (DealStage $s): array => ['value' => $s->value, 'label' => $s->label()], DealStage::cases()),
        ]);
    }

    public function show(Request $request, Deal $deal): Response
    {
        $deal->load(['unit.compound', 'buyer', 'seller', 'request', 'commission', 'events.admin']);
        $locale = app()->getLocale();
        $me = $request->user('admin');
        $isAdmin = $me instanceof AdminUser && $me->isAdmin();
        // "from → to" reads the right way in each direction: the arrow points to where the deal went.
        $arrow = $locale === 'ar' ? ' ← ' : ' → ';
        $person = static fn (?User $u): ?array => $u === null ? null : ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone];
        $c = $deal->commission;

        return Inertia::render('Deals/Show', [
            'deal' => [
                'id' => $deal->id,
                'reference' => $deal->reference(),
                'stage' => $deal->stage->value,
                'stage_label' => $deal->stage->label(),
                'open' => $deal->stage->isOpen(),
                'next' => $deal->stage->next()?->label(),
                'previous' => $deal->stage->previous()?->label(),
                'opened_at' => $deal->opened_at->timezone(self::TZ)->format('Y-m-d H:i'),
                'cancelled_reason' => $deal->cancelled_reason,
            ],
            'track' => array_map(static fn (DealStage $s): array => ['value' => $s->value, 'label' => $s->label()], DealStage::track()),
            'unit' => $deal->unit !== null ? [
                'id' => $deal->unit->id,
                'title' => trim(($deal->unit->code ?? '').' · '.($deal->unit->sub_type?->label() ?? '').' · '.($deal->unit->compound?->nameFor($locale) ?? ''), ' ·'),
                'state' => $deal->unit->state->label(),
            ] : null,
            'buyer' => $person($deal->buyer),
            'seller' => $person($deal->seller),
            'request' => $deal->request !== null ? ['id' => $deal->request->id, 'reference' => RequestsController::reference($deal->request)] : null,
            'commission' => $isAdmin && $c !== null ? [
                'amount' => number_format($c->amount),
                'base' => number_format($c->base_amount),
                'rate' => rtrim(rtrim(number_format($c->rate_bp / 100, 2, '.', ''), '0'), '.').'%',
                'source' => Text::get('commissions.sources.'.$c->source),
                'status' => $c->status->label(),
            ] : null,
            'history' => $deal->events->sortByDesc('id')->values()->map(static fn (DealStageEvent $e): array => [
                'at' => $e->at->timezone(self::TZ)->format('Y-m-d H:i'),
                'event' => ($e->from !== null ? $e->from->label().$arrow : '').$e->to->label(),
                'by' => $e->admin->name ?? Text::get('staff.by_system'),
                'note' => $e->note,
            ])->all(),
        ]);
    }

    /** Open a deal from a matching pair or from a request (G11: one open deal per unit). */
    public function store(Request $request, DealFlow $flow): RedirectResponse
    {
        $request->validate(['unit_id' => ['required', 'integer'], 'buyer_id' => ['required', 'integer'], 'request_id' => ['nullable', 'integer']]);
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        $buyer = User::query()->findOrFail($request->integer('buyer_id'));
        $from = $request->filled('request_id') ? UnitRequest::query()->findOrFail($request->integer('request_id')) : null;
        $deal = $flow->open($unit, $buyer, $from, self::me($request));

        return redirect()->route('dashboard.deals.show', ['deal' => $deal->id])->with('status', Text::get('deals.done.opened', ['reference' => $deal->reference()]));
    }

    public function forward(Request $request, Deal $deal, DealFlow $flow): RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $note = $request->string('note')->trim()->toString();
        $flow->forward($deal, self::me($request), $note !== '' ? $note : null);

        return back()->with('status', Text::get('deals.done.moved'));
    }

    public function back(Request $request, Deal $deal, DealFlow $flow): RedirectResponse
    {
        $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        $flow->back($deal, self::me($request), $request->string('note')->trim()->toString());

        return back()->with('status', Text::get('deals.done.moved'));
    }

    public function cancel(Request $request, Deal $deal, DealFlow $flow): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $flow->cancel($deal, self::me($request), $request->string('reason')->trim()->toString());

        return back()->with('status', Text::get('deals.done.cancelled'));
    }

    private static function me(Request $request): AdminUser
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof AdminUser, 403);

        return $admin;
    }
}
