<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DocumentStatus;
use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPendingChange;
use App\Support\Dashboard\Text;
use App\Support\Review\AfterPublish;
use App\Support\Review\FieldDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * After publishing (PLAN 2.2.7; part 7.3; both roles, can:review-units): one screen with what waits on live units:
 * figure changes to approve or reject (G15), free edits to check (a description can be put back, a photo can be
 * hidden, G17), and documents a seller added after publishing. Oldest first: the longest wait comes first.
 */
class ChangesController extends Controller
{
    private const TZ = 'Africa/Cairo';

    private const LIMIT = 100;

    public function index(): Response
    {
        $locale = app()->getLocale();
        $label = static fn (?Unit $u): string => $u === null ? '' : trim(($u->code ?? '').' · '.($u->sub_type?->label() ?? '').' · '.($u->compound?->nameFor($locale) ?? ''), ' ·');

        $pending = UnitPendingChange::query()->where('state', 'pending')
            ->whereHas('unit', fn ($q) => $q->whereIn('state', UnitState::publicValues()))
            ->with(['unit.compound'])->orderBy('submitted_at')->limit(self::LIMIT)->get()
            ->map(static function (UnitPendingChange $c) use ($label): array {
                $rows = [];
                foreach ($c->changes as $field => $pair) {
                    if (is_string($field) && is_array($pair)) {
                        $rows[] = ['label' => FieldDisplay::label($field), 'from' => FieldDisplay::value($field, $pair[0] ?? null), 'to' => FieldDisplay::value($field, $pair[1] ?? null)];
                    }
                }

                return ['id' => $c->id, 'unit_id' => $c->unit_id, 'unit' => $label($c->unit), 'at' => $c->submitted_at->timezone(self::TZ)->format('Y-m-d H:i'), 'fields' => $rows];
            })->values()->all();

        $recheck = Unit::query()->whereNotNull('recheck_after_edit_at')->whereIn('state', UnitState::publicValues())
            ->with('compound')->orderBy('recheck_after_edit_at')->limit(self::LIMIT)->get()
            ->map(static fn (Unit $u): array => [
                'unit_id' => $u->id,
                'unit' => $label($u),
                'at' => $u->recheck_after_edit_at?->timezone(self::TZ)->format('Y-m-d H:i'),
                'edits' => AfterPublish::freeEdits($u),
                'description' => $u->description,
            ])->values()->all();

        $documents = UnitDocument::query()->where('status', DocumentStatus::Uploaded->value)->whereNull('purged_at')
            ->whereHas('unit', fn ($q) => $q->whereIn('state', UnitState::publicValues()))
            ->with(['unit.compound'])->orderBy('created_at')->limit(self::LIMIT)->get()
            ->map(static fn (UnitDocument $d): array => [
                'id' => $d->id,
                'unit_id' => $d->unit_id,
                'unit' => $label($d->unit),
                'type' => $d->type->label(),
                'at' => $d->created_at?->timezone(self::TZ)->format('Y-m-d H:i'),
            ])->values()->all();

        return Inertia::render('Changes/Index', ['pending' => $pending, 'recheck' => $recheck, 'documents' => $documents]);
    }

    public function approve(Request $request, UnitPendingChange $change, AfterPublish $actions): RedirectResponse
    {
        $actions->approve($change, self::me($request));

        return back()->with('status', Text::get('changes.done.approved'));
    }

    public function reject(Request $request, UnitPendingChange $change, AfterPublish $actions): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $actions->reject($change, self::me($request), $request->string('reason')->trim()->toString());

        return back()->with('status', Text::get('changes.done.rejected'));
    }

    public function checked(Unit $unit, AfterPublish $actions): RedirectResponse
    {
        $actions->checked($unit);

        return back()->with('status', Text::get('changes.done.checked'));
    }

    public function restore(Request $request, Unit $unit, AfterPublish $actions): RedirectResponse
    {
        $request->validate(['log' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $actions->restoreDescription($unit, $request->integer('log'), $request->string('reason')->trim()->toString());

        return back()->with('status', Text::get('changes.done.restored'));
    }

    private static function me(Request $request): AdminUser
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof AdminUser, 403);

        return $admin;
    }
}
