<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\RequestState;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\UnitRequest;
use App\Support\Activity\ActivityLogger;
use App\Support\Dashboard\Text;
use App\Support\Exports\Exports;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The unit request inbox (PLAN 2.3, 2.4; part 7.4; both roles): assign, note, mark contacted, close with a reason.
 * "Matched" is set when a deal opens from the request (part 7.5). Every change is logged; nothing is e-mailed to the
 * buyer from here.
 */
class RequestsController extends Controller
{
    private const TZ = 'Africa/Cairo';

    public function index(Request $request): Response|StreamedResponse
    {
        $table = TableQuery::for($request)
            ->sortable(['created_at'], default: 'created_at', direction: 'desc', tiebreaker: 'id')
            ->filterable(['state' => RequestState::values(), 'mine' => ['1']])
            ->virtual(['mine'])
            ->exportable(Exports::requestColumns(), 'requests')
            ->perPage(default: 25, max: 100);
        $query = UnitRequest::query()->with(['unit', 'buyer']);
        $search = $table->resolvedSearch();
        if ($search !== null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $code = strtoupper((string) preg_replace('/^R-/i', '', $search));
            $query->where(static function (Builder $q) use ($like, $code): void {
                $q->where('public_id', 'like', '%'.addcslashes($code, '%_\\'))
                    ->orWhereHas('unit', fn ($u) => $u->where('code', 'like', $like))
                    ->orWhereHas('buyer', fn ($b) => $b->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            });
        }
        if (($table->resolvedFilters()['mine'] ?? null) === '1') {
            $query->where('assigned_to', $request->user('admin')?->getAuthIdentifier());
        }
        $staff = AdminUser::query()->pluck('name', 'id');
        if ($table->wantsExport()) {
            $names = [];
            foreach (AdminUser::query()->get(['id', 'name']) as $a) {
                $names[$a->id] = $a->name;
            }

            return $table->export($query, static fn (object $r): array => $r instanceof UnitRequest ? Exports::requestRow($r, $names) : [], Exports::loader(['unit', 'buyer']));
        }
        $map = static function (object $r) use ($staff): array {
            if (! $r instanceof UnitRequest) {
                return [];
            }

            return [
                'id' => $r->id,
                'reference' => self::reference($r),
                'unit' => $r->unit?->code,
                'unit_id' => $r->unit_id,
                'buyer' => $r->buyer?->name,
                'readiness' => $r->readiness->label(),
                'state' => $r->state->value,
                'state_label' => $r->state->label(),
                'assigned' => $r->assigned_to !== null && is_string($staff->get($r->assigned_to)) ? (string) $staff->get($r->assigned_to) : null,
                'created_at' => $r->created_at?->timezone(self::TZ)->format('Y-m-d H:i'),
            ];
        };

        return Inertia::render('Requests/Index', [
            'table' => $table->paginate($query, $map),
            'states' => array_map(static fn (RequestState $s): array => ['value' => $s->value, 'label' => $s->label()], RequestState::cases()),
        ]);
    }

    public function show(UnitRequest $unitRequest): Response
    {
        $r = $unitRequest->load(['unit.compound', 'buyer']);
        $locale = app()->getLocale();
        $lines = ActivityLog::query()->where('subject_type', 'unit_requests')->where('subject_id', $r->id)->latest('id')->limit(40)->get();
        $names = AdminUser::query()->pluck('name', 'id');
        $history = $lines->map(static function (ActivityLog $l) use ($names): array {
            $key = 'requests.events.'.str_replace('.', '_', $l->action);
            $text = Text::get($key);
            $by = $l->actor_type === 'admin' ? (is_string($names->get($l->actor_id)) ? (string) $names->get($l->actor_id) : '#'.$l->actor_id) : ($l->actor_type === 'user' ? Text::get('requests.by_buyer') : Text::get('staff.by_system'));

            return ['at' => $l->created_at->timezone(self::TZ)->format('Y-m-d H:i'), 'event' => $text === 'dashboard.'.$key ? $l->action : $text, 'by' => $by, 'note' => $l->reason];
        })->values()->all();

        return Inertia::render('Requests/Show', [
            'request' => [
                'id' => $r->id,
                'reference' => self::reference($r),
                'state' => $r->state->value,
                'state_label' => $r->state->label(),
                'open' => in_array($r->state->value, RequestState::openValues(), true),
                'readiness' => $r->readiness->label(),
                'question' => $r->question,
                'fee' => number_format($r->fee_consent_amount),
                'fee_at' => $r->fee_consent_at->timezone(self::TZ)->format('Y-m-d H:i'),
                'closed_reason' => $r->closed_reason,
                'assigned_to' => $r->assigned_to,
                'staff_note' => $r->staff_note,
                'created_at' => $r->created_at?->timezone(self::TZ)->format('Y-m-d H:i'),
            ],
            'unit' => $r->unit !== null ? [
                'id' => $r->unit->id,
                'title' => trim(($r->unit->code ?? '').' · '.($r->unit->sub_type?->label() ?? '').' · '.($r->unit->compound?->nameFor($locale) ?? ''), ' ·'),
                'state' => $r->unit->state->label(),
            ] : null,
            'buyer' => $r->buyer !== null ? ['id' => $r->buyer->id, 'name' => $r->buyer->name, 'email' => $r->buyer->email, 'phone' => $r->buyer->phone] : null,
            'staff' => AdminUser::query()->where('is_active', true)->orderBy('name')->get()->map(static fn (AdminUser $a): array => ['value' => (string) $a->id, 'label' => $a->name])->values()->all(),
            'history' => $history,
        ]);
    }

    /** Assign, note, mark contacted or close (with a reason). One logged line per save. */
    public function update(Request $request, UnitRequest $unitRequest, ActivityLogger $log): RedirectResponse
    {
        $request->validate([
            'action' => ['required', Rule::in(['save', 'contacted', 'close'])],
            'assigned_to' => ['nullable', 'integer', Rule::exists('admin_users', 'id')->where('is_active', true)],
            'staff_note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $r = $unitRequest;
        $open = in_array($r->state->value, RequestState::openValues(), true);
        $action = $request->string('action')->toString();
        if (! $open && $action !== 'save') {
            throw ValidationException::withMessages(['action' => Text::get('requests.errors.closed')]);
        }
        $reason = $request->string('reason')->trim()->toString();
        if ($action === 'close' && mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => Text::get('units.errors.reason_required')]);
        }
        $before = ['state' => $r->state->value, 'assigned_to' => $r->assigned_to, 'staff_note' => $r->staff_note];
        $assigned = $request->input('assigned_to');
        $r->forceFill([
            'assigned_to' => is_numeric($assigned) ? (int) $assigned : null,
            'staff_note' => $request->string('staff_note')->trim()->toString() !== '' ? $request->string('staff_note')->trim()->toString() : null,
        ]);
        if ($action === 'contacted' && $r->state === RequestState::New) {
            $r->state = RequestState::Contacted;
        }
        if ($action === 'close') {
            $r->forceFill(['state' => RequestState::Closed, 'closed_reason' => $reason]);
        }
        $after = ['state' => $r->state->value, 'assigned_to' => $r->assigned_to, 'staff_note' => $r->staff_note];
        if ($after === $before) {
            return back()->with('status', Text::get('units.done.nothing_changed'));
        }
        $r->save();
        $log->record('unit_request.'.($action === 'save' ? 'updated' : $action), $r, array_diff_assoc($before, $after), array_diff_assoc($after, $before), $action === 'close' ? $reason : null);

        return back()->with('status', Text::get('staff.saved'));
    }

    public static function reference(UnitRequest $r): string
    {
        return 'R-'.strtoupper(substr((string) $r->public_id, -6));
    }
}
