<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CommissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Support\Activity\ActivityLogger;
use App\Support\Dashboard\Text;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Commissions (PLAN 2.4; part 7.5; admin only, can:manage-commissions; G7, G8). One record per deal, frozen at opening;
 * status and reference only: due at completion, then invoiced and collected with a date and a reference, or waived
 * with a reason. No invoice document and no part-payments. Every change is logged.
 */
class CommissionsController extends Controller
{
    /** Allowed moves: from => list of next statuses. */
    private const MOVES = [
        'due' => ['invoiced', 'waived'],
        'invoiced' => ['collected', 'waived'],
    ];

    public function index(Request $request): Response
    {
        $table = TableQuery::for($request)
            ->sortable(['updated_at', 'amount'], default: 'updated_at', direction: 'desc', tiebreaker: 'id')
            ->filterable(['status' => CommissionStatus::values()])
            ->perPage(default: 25, max: 100);
        $map = static function (object $c): array {
            if (! $c instanceof Commission) {
                return [];
            }

            return [
                'id' => $c->id,
                'deal_id' => $c->deal_id,
                'deal' => $c->deal?->reference(),
                'unit' => $c->deal?->unit?->code,
                'amount' => number_format($c->amount),
                'base' => number_format($c->base_amount),
                'rate' => rtrim(rtrim(number_format($c->rate_bp / 100, 2, '.', ''), '0'), '.').'%',
                'source' => Text::get('commissions.sources.'.$c->source),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'moves' => self::MOVES[$c->status->value] ?? [],
                'invoiced_on' => $c->invoiced_on?->format('Y-m-d'),
                'collected_on' => $c->collected_on?->format('Y-m-d'),
                'reference' => $c->reference,
                'waived_reason' => $c->waived_reason,
                'notes' => $c->notes,
            ];
        };
        $totals = [];
        foreach (CommissionStatus::cases() as $status) {
            $sum = Commission::query()->where('status', $status->value)->sum('amount');
            $totals[] = ['status' => $status->value, 'label' => $status->label(), 'amount' => number_format((int) $sum)];
        }

        return Inertia::render('Commissions/Index', [
            'table' => $table->paginate(Commission::query()->with('deal.unit'), $map),
            'totals' => $totals,
        ]);
    }

    public function update(Request $request, Commission $commission, ActivityLogger $log): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['invoiced', 'collected', 'waived'])],
            'on' => ['nullable', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $to = $request->string('status')->toString();
        if (! in_array($to, self::MOVES[$commission->status->value] ?? [], true)) {
            throw ValidationException::withMessages(['status' => Text::get('commissions.errors.move')]);
        }
        $on = $request->string('on')->toString();
        $reference = $request->string('reference')->trim()->toString();
        $reason = $request->string('reason')->trim()->toString();
        if (in_array($to, ['invoiced', 'collected'], true) && ($on === '' || $reference === '')) {
            throw ValidationException::withMessages(['reference' => Text::get('commissions.errors.date_reference')]);
        }
        if ($to === 'waived' && mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => Text::get('units.errors.reason_required')]);
        }
        $before = ['status' => $commission->status->value, 'reference' => $commission->reference];
        $fill = ['status' => CommissionStatus::from($to)];
        if ($to === 'invoiced') {
            $fill += ['invoiced_on' => $on, 'reference' => $reference];
        }
        if ($to === 'collected') {
            $fill += ['collected_on' => $on, 'reference' => $reference];
        }
        if ($to === 'waived') {
            $fill += ['waived_reason' => $reason];
        }
        $notes = $request->string('notes')->trim()->toString();
        if ($notes !== '') {
            $fill += ['notes' => $notes];
        }
        $commission->forceFill($fill)->save();
        $log->record('commission.'.$to, $commission, $before, ['status' => $to, 'on' => $on !== '' ? $on : null, 'reference' => $commission->reference], $to === 'waived' ? $reason : null);

        return back()->with('status', Text::get('staff.saved'));
    }
}
