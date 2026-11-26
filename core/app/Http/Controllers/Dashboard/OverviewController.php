<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\MailState;
use App\Enums\TextSource;
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

        // Staff read names, not keys (audit D12); the key stays as a hint for whoever edits the setting.
        $provisional = [];
        foreach (array_keys(array_filter($settings->all(), static fn (array $s): bool => $s['provisional'])) as $key) {
            $label = __("dashboard.setting_labels.{$key}");
            $provisional[] = ['key' => $key, 'label' => is_string($label) && $label !== "dashboard.setting_labels.{$key}" ? $label : $key];
        }

        // Site texts by state (K7): how many are still placeholders, written by us for the client's review, or awaiting
        // legal review; the last are listed by key, with where each is used.
        $bySource = SiteText::query()->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source');
        $texts = [];
        foreach (TextSource::cases() as $source) {
            $count = $bySource->get($source->value, 0);
            $texts[] = ['source' => $source->value, 'label' => $source->label(), 'count' => is_numeric($count) ? (int) $count : 0];
        }
        $legal = SiteText::query()->where('source', TextSource::LegalReview->value)->orderBy('group')->orderBy('key')->get(['key', 'note'])
            ->map(static fn (SiteText $t): array => ['key' => $t->key, 'note' => is_string($t->note) ? $t->note : ''])->values()->all();

        return Inertia::render('Overview', [
            'units' => $units,
            'texts' => $texts,
            'legalReview' => $legal,
            'provisionalSettings' => $provisional,
            'mailProblems' => MailOutbox::query()
                ->whereIn('state', [MailState::Failed->value, MailState::Dead->value])
                ->count(),
        ]);
    }
}
