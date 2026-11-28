<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\MainType;
use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\BuyerBudget;
use App\Models\Unit;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Dashboard\Text;
use App\Support\Matching\Matcher;
use App\Support\People\Suspension;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sellers and buyers (PLAN 1.3, 2.7; part 7.4; G4). Both roles see the people, their units, budget and requests;
 * only an admin suspends (with a reason) or lifts a suspension, on its own routes (can:suspend-users).
 */
class PeopleController extends Controller
{
    private const TZ = 'Africa/Cairo';

    public function index(Request $request): Response
    {
        $table = TableQuery::for($request)
            ->sortable(['created_at', 'name'], default: 'created_at', direction: 'desc', tiebreaker: 'id')
            ->filterable(['kind' => ['sellers', 'buyers', 'suspended']])
            ->virtual(['kind'])
            ->perPage(default: 25, max: 100);
        $query = User::query()
            ->withCount([
                'units as units_count' => static fn (Builder $q): Builder => $q->where('state', '!=', UnitState::Draft->value),
                'requests as requests_count',
            ])
            ->withExists('budget');
        $search = $table->resolvedSearch();
        if ($search !== null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like);
            });
        }
        match ($table->resolvedFilters()['kind'] ?? null) {
            'sellers' => $query->whereHas('units', fn ($q) => $q->where('state', '!=', UnitState::Draft->value)),
            'buyers' => $query->where(fn ($q) => $q->has('budget')->orHas('requests')),
            'suspended' => $query->whereNotNull('suspended_at'),
            default => null,
        };

        $map = static function (object $user): array {
            if (! $user instanceof User) {
                return [];
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'verified' => $user->hasVerifiedEmail(),
                'suspended' => $user->isSuspended(),
                'units' => self::count($user->getAttribute('units_count')),
                'requests' => self::count($user->getAttribute('requests_count')),
                'budget' => (bool) $user->getAttribute('budget_exists'),
                'created_at' => $user->created_at?->timezone(self::TZ)->format('Y-m-d'),
            ];
        };

        return Inertia::render('People/Index', ['table' => $table->paginate($query, $map)]);
    }

    public function show(Request $request, User $user): Response
    {
        $locale = app()->getLocale();
        $units = Unit::query()->where('seller_id', $user->id)->where('state', '!=', UnitState::Draft->value)->with('compound')->latest('id')->get()
            ->map(static fn (Unit $u): array => [
                'id' => $u->id,
                'label' => trim(($u->code ?? ($u->serial !== null ? sprintf('#%04d', $u->serial) : '')).' · '.($u->sub_type?->label() ?? '').' · '.($u->compound?->nameFor($locale) ?? $u->compound_other ?? ''), ' ·'),
                'state' => $u->state->label(),
                'held' => $u->held_at !== null,
            ])->values()->all();
        $drafts = Unit::query()->where('seller_id', $user->id)->where('state', UnitState::Draft->value)->count();
        $requests = UnitRequest::query()->where('buyer_id', $user->id)->with('unit')->latest('id')->limit(50)->get()
            ->map(static fn (UnitRequest $r): array => [
                'id' => $r->id,
                'reference' => 'R-'.strtoupper(substr($r->public_id, -6)),
                'unit' => $r->unit?->code,
                'state' => $r->state->label(),
                'at' => $r->created_at?->timezone(self::TZ)->format('Y-m-d H:i'),
            ])->values()->all();
        $budget = BuyerBudget::query()->where('user_id', $user->id)->with(['areas', 'compounds'])->first();

        $lines = ActivityLog::query()->where('subject_type', 'users')->where('subject_id', $user->id)->latest('id')->limit(40)->get();
        $admins = AdminUser::query()->whereKey($lines->where('actor_type', 'admin')->pluck('actor_id')->filter()->unique()->all())->pluck('name', 'id');
        $history = $lines->map(static function (ActivityLog $l) use ($admins, $user): array {
            $key = 'people.events.'.str_replace('.', '_', $l->action);
            $text = Text::get($key);
            $by = match ($l->actor_type) {
                'admin' => is_string($admins->get($l->actor_id)) ? (string) $admins->get($l->actor_id) : '#'.$l->actor_id,
                'user' => $l->actor_id === $user->id ? Text::get('people.by_person') : Text::get('units.by_user'),
                default => Text::get('staff.by_system'),
            };

            return ['at' => $l->created_at->timezone(self::TZ)->format('Y-m-d H:i'), 'event' => $text === 'dashboard.'.$key ? $l->action : $text, 'by' => $by, 'note' => $l->reason];
        })->values()->all();

        $me = $request->user('admin');

        return Inertia::render('People/Show', [
            'person' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->preferredLocale(),
                'verified' => $user->hasVerifiedEmail(),
                'created_at' => $user->created_at?->timezone(self::TZ)->format('Y-m-d H:i'),
                'suspended_at' => $user->suspended_at?->timezone(self::TZ)->format('Y-m-d H:i'),
                'suspended_reason' => $user->suspended_reason,
            ],
            'units' => $units,
            'drafts' => $drafts,
            'requests' => $requests,
            'budget' => $budget instanceof BuyerBudget ? self::budget($budget, $locale) : null,
            'history' => $history,
            'canSuspend' => $me instanceof AdminUser && $me->isAdmin(),
        ]);
    }

    /** G4: admin only (can:suspend-users), with a reason. */
    public function suspend(Request $request, User $user, Suspension $suspension): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $admin = $request->user('admin');
        abort_unless($admin instanceof AdminUser, 403);
        $suspension->suspend($user, $admin, $request->string('reason')->trim()->toString());

        return back()->with('status', Text::get('people.done.suspended'));
    }

    public function lift(Request $request, User $user, Suspension $suspension): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $suspension->lift($user, $request->string('reason')->trim()->toString());

        return back()->with('status', Text::get('people.done.lifted'));
    }

    /**
     * A budget in words, for the people and budget screens.
     *
     * @return array<string, mixed>
     */
    public static function budget(BuyerBudget $b, string $locale): array
    {
        return [
            'id' => $b->id,
            'user_id' => $b->user_id,
            'cash' => number_format($b->cash_available),
            'installment' => number_format($b->max_installment),
            'frequency' => $b->installment_frequency->label(),
            'monthly' => number_format(Matcher::monthly($b->max_installment, $b->installment_frequency)),
            'types' => array_values(array_map(static fn (string $t): string => MainType::tryFrom($t)?->label() ?? $t, array_filter($b->main_types, is_string(...)))),
            'areas' => $b->areas->map(fn ($a): string => $a->nameFor($locale))->values()->all(),
            'compounds' => $b->compounds->map(fn ($c): string => $c->nameFor($locale))->values()->all(),
            'target' => $b->target_price !== null ? number_format($b->target_price) : null,
            'bedrooms' => $b->min_bedrooms,
            'delivery' => $b->delivery_pref->label(),
            'updated_at' => $b->updated_at?->timezone(self::TZ)->format('Y-m-d H:i'),
        ];
    }

    private static function count(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
