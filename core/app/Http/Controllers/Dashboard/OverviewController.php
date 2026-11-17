<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\MailState;
use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Models\MailOutbox;
use App\Models\SiteText;
use App\Models\Unit;
use App\Support\Settings\Settings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard home: what waits for action. Phase 1 shows counts only; the screens behind them come in their
 * phases (PLAN 7).
 */
class OverviewController extends Controller
{
    public function __invoke(Settings $settings): Response
    {
        $byState = Unit::query()
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

        $units = [];
        foreach (UnitState::cases() as $state) {
            $count = $byState->get($state->value, 0);
            $units[] = [
                'state' => $state->value,
                'label' => $state->label(),
                'count' => is_numeric($count) ? (int) $count : 0,
            ];
        }

        $provisional = array_keys(array_filter($settings->all(), static fn (array $s): bool => $s['provisional']));

        return Inertia::render('Overview', [
            'units' => $units,
            'placeholderTexts' => SiteText::query()->where('is_placeholder', true)->count(),
            'provisionalSettings' => $provisional,
            'mailProblems' => MailOutbox::query()
                ->whereIn('state', [MailState::Failed->value, MailState::Dead->value])
                ->count(),
        ]);
    }
}
