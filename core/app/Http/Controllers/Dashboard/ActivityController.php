<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\User;
use App\Support\Table\TableQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The activity log screen (PLAN 1.3, admin only): every write, newest first, filterable by actor kind and action,
 * searchable by action or subject, with a date range; exportable as CSV.
 */
class ActivityController extends Controller
{
    private const ACTOR_TYPES = ['admin', 'user', 'system'];

    public function __invoke(Request $request): Response|StreamedResponse
    {
        $actions = array_values(array_filter(
            ActivityLog::query()->distinct()->orderBy('action')->pluck('action')->all(),
            is_string(...),
        ));

        $table = TableQuery::for($request)
            ->sortable(['created_at', 'id', 'action'], default: 'created_at', direction: 'desc', tiebreaker: 'id')
            ->searchable(['action', 'subject_type', 'reason'])
            ->filterable(['actor_type' => self::ACTOR_TYPES, 'action' => $actions, 'from' => null, 'to' => null])
            ->virtual(['from', 'to'])
            ->exportable([
                'created_at' => 'time',
                'actor' => 'actor',
                'action' => 'action',
                'subject' => 'subject',
                'reason' => 'reason',
            ], 'activity')
            ->perPage(default: 25, max: 100);

        $filters = $table->resolvedFilters();
        $query = ActivityLog::query();
        if (is_string($filters['from'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from']) === 1) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (is_string($filters['to'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to']) === 1) {
            $query->where('created_at', '<=', $filters['to'].' 23:59:59');
        }

        $names = [];
        $prepare = static function (array $rows) use (&$names): void {
            $admins = [];
            $users = [];
            foreach ($rows as $row) {
                if ($row instanceof ActivityLog && $row->actor_id !== null) {
                    $row->actor_type === 'admin' ? $admins[] = $row->actor_id : $users[] = $row->actor_id;
                }
            }
            foreach (AdminUser::query()->whereKey($admins)->get() as $admin) {
                $names['admin:'.$admin->id] = $admin->name;
            }
            foreach (User::query()->whereKey($users)->get() as $user) {
                $names['user:'.$user->id] = $user->name;
            }
        };

        $map = static function (object $row) use (&$names): array {
            if (! $row instanceof ActivityLog) {
                return [];
            }
            $actorKey = $row->actor_type.':'.($row->actor_id ?? '');

            return [
                'id' => $row->id,
                'time' => $row->created_at->timezone('Africa/Cairo')->format('Y-m-d H:i'),
                'actor_type' => $row->actor_type,
                'actor' => $names[$actorKey] ?? ($row->actor_type === 'system' ? null : '#'.($row->actor_id ?? '')),
                'action' => $row->action,
                'subject' => $row->subject_type !== null ? $row->subject_type.($row->subject_id !== null ? ' #'.$row->subject_id : '') : null,
                'changes' => self::changes($row->before, $row->after),
                'reason' => $row->reason,
            ];
        };

        if ($table->wantsExport()) {
            return $table->export($query, $map, $prepare);
        }

        return Inertia::render('Activity/Index', [
            'table' => $table->paginate($query, $map, $prepare),
            'actions' => $actions,
            'actorTypes' => self::ACTOR_TYPES,
        ]);
    }

    /**
     * Field by field: before and after, as short display strings.
     *
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>|null  $after
     * @return list<array{field: string, from: string|null, to: string|null}>
     */
    private static function changes(?array $before, ?array $after): array
    {
        $fields = array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? [])));
        $out = [];
        foreach ($fields as $field) {
            $out[] = [
                'field' => (string) $field,
                'from' => self::display($before[$field] ?? null),
                'to' => self::display($after[$field] ?? null),
            ];
        }

        return $out;
    }

    private static function display(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $text = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);

        return mb_strlen($text) > 160 ? mb_substr($text, 0, 157).'…' : $text;
    }
}
