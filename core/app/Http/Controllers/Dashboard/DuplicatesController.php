<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DuplicateAttempt;
use App\Models\Unit;
use App\Models\User;
use App\Support\Table\TableQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Duplicate attempts (PLAN 2.4, 3.4; part 7.2; both roles): every blocked step-1 or submit attempt on a contract key
 * another active unit holds, and every warning on a contract file whose sha256 another unit already has. The seller
 * never learned which unit matched; the team sees both. Read only: a block is never lifted from here (Q1).
 */
class DuplicatesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $table = TableQuery::for($request)
            ->sortable(['created_at', 'id'], default: 'created_at', direction: 'desc', tiebreaker: 'id')
            ->filterable(['key_kind' => ['contract', 'unit', 'file_sha256'], 'blocked' => ['0', '1']])
            ->perPage(default: 25, max: 100);

        $units = [];
        $users = [];
        $prepare = static function (array $rows) use (&$units, &$users): void {
            $ids = [];
            $userIds = [];
            foreach ($rows as $row) {
                if ($row instanceof DuplicateAttempt) {
                    array_push($ids, $row->unit_id, $row->matched_unit_id);
                    $userIds[] = $row->user_id;
                }
            }
            foreach (Unit::query()->whereKey(array_filter($ids))->get(['id', 'serial', 'code', 'state']) as $unit) {
                $units[$unit->id] = $unit;
            }
            foreach (User::query()->whereKey(array_filter($userIds))->get(['id', 'name', 'email']) as $user) {
                $users[$user->id] = $user;
            }
        };
        $ref = static function (?int $id) use (&$units): ?array {
            $unit = $id !== null ? ($units[$id] ?? null) : null;
            if (! $unit instanceof Unit) {
                return null;
            }

            return [
                'id' => $unit->id,
                'label' => $unit->code ?? ($unit->serial !== null ? sprintf('#%04d', $unit->serial) : '#'.$unit->id),
                'state' => $unit->state->label(),
                'openable' => $unit->state->value !== 'draft',
            ];
        };
        $map = static function (object $row) use ($ref, &$users): array {
            if (! $row instanceof DuplicateAttempt) {
                return [];
            }
            $user = $users[$row->user_id] ?? null;

            return [
                'id' => $row->id,
                'at' => $row->created_at->timezone('Africa/Cairo')->format('Y-m-d H:i'),
                'kind' => $row->key_kind,
                'stage' => $row->stage,
                'blocked' => (bool) $row->blocked,
                'user' => $user instanceof User ? $user->name.' · '.$user->email : null,
                'unit' => $ref($row->unit_id),
                'matched' => $ref($row->matched_unit_id),
            ];
        };

        return Inertia::render('Duplicates/Index', ['table' => $table->paginate(DuplicateAttempt::query(), $map, $prepare)]);
    }
}
