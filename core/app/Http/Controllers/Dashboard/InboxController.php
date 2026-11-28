<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\ContactMessage;
use App\Models\PartnershipRequest;
use App\Support\Activity\ActivityLogger;
use App\Support\Dashboard\Text;
use App\Support\Settings\Settings;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contact messages and partnership requests (PLAN 2.4, 2.6; part 7.6; G5; both roles, can:work-clients). The team
 * e-mail keeps carrying each message in full (G5); here the team reads them, keeps a note and marks them handled.
 * Handled messages are deleted after `handled_message_retention_months` by `messages:purge`. Every change is logged.
 */
class InboxController extends Controller
{
    private const TZ = 'Africa/Cairo';

    public function index(Request $request, string $kind, Settings $settings): Response
    {
        $table = TableQuery::for($request)
            ->sortable(['created_at'], default: 'created_at', direction: 'desc', tiebreaker: 'id')
            ->filterable(['state' => ['new', 'handled']])
            ->perPage(default: 25, max: 100);
        $query = self::model($kind)::query();
        $search = $table->resolvedSearch();
        if ($search !== null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $columns = $kind === 'contact' ? ['name', 'email', 'phone', 'message'] : ['company', 'contact_name', 'email', 'phone', 'message'];
            $query->where(static function (Builder $q) use ($columns, $like): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }
        $names = AdminUser::query()->pluck('name', 'id');
        $map = static function (object $row) use ($names): array {
            if (! $row instanceof ContactMessage && ! $row instanceof PartnershipRequest) {
                return [];
            }
            $handledBy = $row->getAttribute('handled_by');

            return [
                'id' => $row->id,
                'name' => $row instanceof PartnershipRequest ? $row->contact_name : $row->name,
                'company' => $row instanceof PartnershipRequest ? $row->company : null,
                'job_title' => $row instanceof PartnershipRequest ? $row->job_title : null,
                'projects' => $row instanceof PartnershipRequest ? $row->projects_count : null,
                'email' => $row->email,
                'phone' => $row->phone,
                'message' => $row->message,
                'state' => $row->state,
                'staff_note' => $row->getAttribute('staff_note'),
                'handled_by' => is_numeric($handledBy) && is_string($names->get((int) $handledBy)) ? (string) $names->get((int) $handledBy) : null,
                'created_at' => $row->created_at?->timezone(self::TZ)->format('Y-m-d H:i'),
            ];
        };

        return Inertia::render('Inbox/Index', [
            'kind' => $kind,
            'table' => $table->paginate($query, $map),
            'retentionMonths' => $settings->int('handled_message_retention_months'),
        ]);
    }

    /** Save a note, mark handled, or reopen. */
    public function update(Request $request, string $kind, int $id, ActivityLogger $log): RedirectResponse
    {
        $request->validate(['handled' => ['required', 'boolean'], 'staff_note' => ['nullable', 'string', 'max:2000']]);
        $row = self::model($kind)::query()->findOrFail($id);
        $admin = $request->user('admin');
        abort_unless($admin instanceof AdminUser, 403);
        $handled = $request->boolean('handled');
        $note = $request->string('staff_note')->trim()->toString();
        $before = ['state' => $row->getAttribute('state'), 'staff_note' => $row->getAttribute('staff_note')];
        $row->forceFill([
            'state' => $handled ? 'handled' : 'new',
            'handled_at' => $handled ? ($row->getAttribute('handled_at') ?? now()) : null,
            'handled_by' => $handled ? ($row->getAttribute('handled_by') ?? $admin->id) : null,
            'staff_note' => $note !== '' ? $note : null,
        ]);
        if (! $row->isDirty()) {
            return back()->with('status', Text::get('units.done.nothing_changed'));
        }
        $row->save();
        $after = ['state' => $row->getAttribute('state'), 'staff_note' => $row->getAttribute('staff_note')];
        $log->record(($kind === 'contact' ? 'contact_message' : 'partnership_request').'.'.($before['state'] !== $after['state'] ? ($handled ? 'handled' : 'reopened') : 'noted'), $row, $before, $after);

        return back()->with('status', Text::get('staff.saved'));
    }

    /**
     * @return class-string<ContactMessage>|class-string<PartnershipRequest>
     */
    private static function model(string $kind): string
    {
        return match ($kind) {
            'contact' => ContactMessage::class,
            'partnership' => PartnershipRequest::class,
            default => abort(404),
        };
    }
}
