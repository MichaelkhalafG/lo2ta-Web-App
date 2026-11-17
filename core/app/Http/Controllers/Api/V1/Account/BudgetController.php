<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Enums\DeliveryPreference;
use App\Enums\InstallmentFrequency;
use App\Enums\MainType;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Area;
use App\Models\BuyerBudget;
use App\Models\Compound;
use App\Support\Activity\ActivityLogger;
use App\Support\Mail\Outbox;
use App\Support\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The buyer's budget (PLAN 2.3): cash available now, the installment they can carry, and what they look for.
 * One per account, edited any time. The team is told by e-mail when a budget is first registered; matching is the
 * admin's work (phase 7), with no automatic alerts (out of scope).
 */
class BudgetController extends Controller
{
    private const MONEY_MAX = 1_000_000_000;

    private const INSTALLMENT_MAX = 100_000_000;

    public function show(Request $request): JsonResponse
    {
        $budget = MeController::user($request)->budget()->with(['areas', 'compounds'])->first();

        return response()->json(['data' => $budget instanceof BuyerBudget ? self::present($budget) : null]);
    }

    public function update(Request $request, Settings $settings, Outbox $outbox, ActivityLogger $log): JsonResponse
    {
        $user = MeController::user($request);
        $request->validate([
            'cash_available' => ['required', 'integer', 'min:0', 'max:'.self::MONEY_MAX],
            'max_installment' => ['required', 'integer', 'min:0', 'max:'.self::INSTALLMENT_MAX],
            'installment_frequency' => ['required', Rule::in(InstallmentFrequency::values())],
            'main_types' => ['required', 'array', 'min:1', 'max:4'],
            'main_types.*' => ['distinct', Rule::in(MainType::values())],
            'areas' => ['required', 'array', 'min:1', 'max:'.$settings->int('budget_max_areas')],
            'areas.*' => ['distinct', 'string', Rule::exists('areas', 'slug')->where('is_active', true)],
            'compounds' => ['nullable', 'array', 'max:20'],
            'compounds.*' => ['distinct', 'string', Rule::exists('compounds', 'slug')->where('is_active', true)],
            'target_price' => ['nullable', 'integer', 'min:1', 'max:'.self::MONEY_MAX],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:10'],
            'delivery_pref' => ['required', Rule::in(DeliveryPreference::values())],
        ]);

        /** @var list<string> $types */
        $types = array_values(array_filter((array) $request->input('main_types'), is_string(...)));
        /** @var list<string> $areaSlugs */
        $areaSlugs = array_values(array_filter((array) $request->input('areas'), is_string(...)));
        /** @var list<string> $compoundSlugs */
        $compoundSlugs = array_values(array_filter((array) $request->input('compounds', []), is_string(...)));

        $budget = $user->budget()->first() ?? new BuyerBudget(['delivery_pref' => DeliveryPreference::Either]);
        $first = ! $budget->exists;
        $budget->fill([
            'cash_available' => $request->integer('cash_available'),
            'max_installment' => $request->integer('max_installment'),
            'installment_frequency' => $request->string('installment_frequency')->toString(),
            'main_types' => $types,
            'target_price' => $request->filled('target_price') ? $request->integer('target_price') : null,
            'min_bedrooms' => $request->filled('min_bedrooms') ? $request->integer('min_bedrooms') : null,
            'delivery_pref' => $request->string('delivery_pref')->toString(),
        ]);
        $budget->user()->associate($user);
        $budget->save();
        $budget->areas()->sync(Area::query()->whereIn('slug', $areaSlugs)->pluck('id')->all());
        $budget->compounds()->sync(Compound::query()->whereIn('slug', $compoundSlugs)->pluck('id')->all());

        $log->record($first ? 'budget.created' : 'budget.updated', $budget, null, [
            'cash_available' => $budget->cash_available,
            'max_installment' => $budget->max_installment,
            'areas' => $areaSlugs,
        ]);

        if ($first) {
            // RUN D14: the team is every active admin account until the client names a team mailbox.
            foreach (AdminUser::query()->where('is_active', true)->get() as $admin) {
                $outbox->send($admin->email, $admin->name, in_array($admin->locale, ['ar', 'en'], true) ? $admin->locale : 'ar', 'admin.new_budget', [
                    'buyer' => $user->name,
                    'cash' => number_format($budget->cash_available),
                    'installment' => number_format($budget->max_installment),
                    'verified' => $user->hasVerifiedEmail() ? '✓' : '✗',
                ]);
            }
        }

        return response()->json(['data' => self::present($budget->load(['areas', 'compounds']))], $first ? 201 : 200);
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(BuyerBudget $budget): array
    {
        return [
            'cash_available' => $budget->cash_available,
            'max_installment' => $budget->max_installment,
            'installment_frequency' => $budget->installment_frequency->value,
            'main_types' => $budget->main_types,
            'areas' => $budget->areas->pluck('slug')->values()->all(),
            'compounds' => $budget->compounds->pluck('slug')->values()->all(),
            'target_price' => $budget->target_price,
            'min_bedrooms' => $budget->min_bedrooms,
            'delivery_pref' => $budget->delivery_pref->value,
            'updated_at' => $budget->updated_at?->toIso8601String(),
        ];
    }
}
