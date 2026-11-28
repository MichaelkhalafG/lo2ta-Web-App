<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\DuplicateAttempt;
use App\Models\DuplicateOverride;
use App\Models\Unit;
use App\Models\User;
use App\Support\Dashboard\Text;
use App\Support\Review\DuplicateOverrides;
use App\Support\Table\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Duplicate attempts (PLAN 2.4, 3.4; part 7.2; both roles): every blocked step-1 or submit attempt on a contract key
 * another active unit holds, and every warning on a contract file whose sha256 another unit already has. The seller
 * never learned which unit matched; the team sees both. An admin may override a block with a reason (Q23, D113-D115):
 * the route carries the admin-only `override-duplicates` ability; the seller is told nothing.
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
        $overrides = [];
        $prepare = static function (array $rows) use (&$units, &$users, &$overrides): void {
            $ids = [];
            $userIds = [];
            $rowUnits = [];
            foreach ($rows as $row) {
                if ($row instanceof DuplicateAttempt) {
                    array_push($ids, $row->unit_id, $row->matched_unit_id);
                    $userIds[] = $row->user_id;
                    if ($row->unit_id !== null) {
                        $rowUnits[] = $row->unit_id;
                    }
                }
            }
            foreach (Unit::query()->whereKey(array_filter($ids))->get(['id', 'serial', 'code', 'state']) as $unit) {
                $units[$unit->id] = $unit;
            }
            foreach (User::query()->whereKey(array_filter($userIds))->get(['id', 'name', 'email']) as $user) {
                $users[$user->id] = $user;
            }
            if ($rowUnits !== []) {
                foreach (DuplicateOverride::query()->whereIn('unit_id', $rowUnits)->with('creator:id,name')->get() as $override) {
                    $overrides[$override->unit_id.':'.$override->matched_unit_id] = $override;
                }
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
        $map = static function (object $row) use ($ref, &$users, &$units, &$overrides): array {
            if (! $row instanceof DuplicateAttempt) {
                return [];
            }
            $user = $users[$row->user_id] ?? null;
            $override = $overrides[$row->unit_id.':'.$row->matched_unit_id] ?? null;
            $unit = $row->unit_id !== null ? ($units[$row->unit_id] ?? null) : null;

            return [
                'id' => $row->id,
                'at' => $row->created_at->timezone('Africa/Cairo')->format('Y-m-d H:i'),
                'kind' => $row->key_kind,
                'stage' => $row->stage,
                'blocked' => (bool) $row->blocked,
                'user' => $user instanceof User ? $user->name.' · '.$user->email : null,
                'unit' => $ref($row->unit_id),
                'matched' => $ref($row->matched_unit_id),
                'overridable' => DuplicateOverrides::canOverride($row, $unit instanceof Unit ? $unit : null, $override instanceof DuplicateOverride),
                'override' => $override instanceof DuplicateOverride ? self::overrideRow($override) : null,
            ];
        };

        return Inertia::render('Duplicates/Index', ['table' => $table->paginate(DuplicateAttempt::query(), $map, $prepare)]);
    }

    /** Admin only (route ability `override-duplicates`): let the attempt's unit go on despite this one match. */
    public function override(Request $request, DuplicateAttempt $attempt, DuplicateOverrides $overrides): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $admin = $request->user('admin');
        abort_unless($admin instanceof AdminUser, 403);
        $overrides->override($attempt, $admin, (string) $request->string('reason'));

        return back()->with('status', Text::get('duplicates.overridden'));
    }

    /**
     * @return array{at: string, by: string|null, reason: string}
     */
    public static function overrideRow(DuplicateOverride $override): array
    {
        $creator = $override->creator;

        return [
            'at' => $override->created_at->timezone('Africa/Cairo')->format('Y-m-d H:i'),
            'by' => $creator instanceof AdminUser ? $creator->name : null,
            'reason' => $override->reason,
        ];
    }
}
