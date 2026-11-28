<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\AdminRole as AdminRoleCode;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminPasswordLink;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Support\Dashboard\Text;
use App\Support\Staff\StaffPasswords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff management (PLAN 1.3, 2.7; admin only, can:manage-admins). New staff are invited with a one-use link (G14);
 * a forgotten password gets a reset link (G13); no one ever types another person's password. Accounts are never
 * deleted (the activity log keeps pointing at them): an admin switches them off. No one can switch off or demote
 * themselves, or the last active admin. Every change is logged.
 */
class StaffController extends Controller
{
    /** Staff read times in Cairo time, like every dashboard screen. */
    private const TZ = 'Africa/Cairo';

    public function index(Request $request): Response
    {
        $me = self::me($request);
        $staff = AdminUser::query()->with('role')->orderByDesc('is_active')->orderBy('name')->get();
        $links = AdminPasswordLink::query()->whereNull('used_at')->where('expires_at', '>', now())->get()->keyBy('admin_user_id');
        $names = [];
        foreach ($staff as $member) {
            $names[$member->id] = $member->name;
        }
        // The last ten events per person (sign-ins and sign-outs left out), already in words.
        $history = [];
        $lines = ActivityLog::query()
            ->where('subject_type', 'admin_users')->whereIn('subject_id', array_keys($names))
            ->whereNotIn('action', ['admin.login', 'admin.logout'])
            ->latest('id')->limit(300)->get();
        foreach ($lines as $line) {
            $id = $line->subject_id;
            if ($id === null || count($history[$id] ?? []) >= 10) {
                continue;
            }
            $by = $line->actor_type === 'admin' && $line->actor_id !== null ? ($names[$line->actor_id] ?? '#'.$line->actor_id) : Text::get('staff.by_system');
            $history[$id][] = ['action' => self::event($line->action), 'at' => $line->created_at->timezone(self::TZ)->format('Y-m-d H:i'), 'by' => $by, 'reason' => $line->reason];
        }

        return Inertia::render('Staff/Index', [
            'staff' => $staff->map(static function (AdminUser $a) use ($me, $links, $history): array {
                $link = $links->get($a->id);

                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'email' => $a->email,
                    'role' => $a->roleCode()->value,
                    'role_label' => $a->roleCode()->label(),
                    'locale' => $a->locale === 'en' ? 'en' : 'ar',
                    'is_active' => $a->is_active,
                    'is_self' => $a->id === $me->id,
                    'last_login_at' => $a->last_login_at?->timezone(self::TZ)->format('Y-m-d H:i'),
                    'link_until' => $link instanceof AdminPasswordLink ? $link->expires_at->timezone(self::TZ)->format('Y-m-d H:i') : null,
                    'history' => $history[$a->id] ?? [],
                ];
            })->values()->all(),
            'roles' => array_map(static fn (AdminRoleCode $r): array => ['value' => $r->value, 'label' => $r->label()], AdminRoleCode::cases()),
        ]);
    }

    /** Invite: the account starts with an unusable random password and gets a one-use set-password link. */
    public function store(Request $request, StaffPasswords $passwords): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admin_users', 'email')],
            'role' => ['required', Rule::in(AdminRoleCode::values())],
            'locale' => ['required', Rule::in(['ar', 'en'])],
        ]);
        $admin = AdminUser::query()->create([
            'admin_role_id' => self::roleId($request->string('role')->toString()),
            'name' => $request->string('name')->trim()->toString(),
            'email' => Str::lower($request->string('email')->trim()->toString()),
            'password' => Hash::make(bin2hex(random_bytes(32))),
            'locale' => $request->string('locale')->toString(),
            'is_active' => true,
        ]);
        $passwords->sendLink($admin, AdminPasswordLink::INVITE, self::me($request));

        return back()->with('status', Text::get('staff.invited', ['email' => $admin->email]));
    }

    public function update(Request $request, AdminUser $staff): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'role' => ['required', Rule::in(AdminRoleCode::values())],
            'locale' => ['required', Rule::in(['ar', 'en'])],
            'is_active' => ['required', 'boolean'],
        ]);
        $role = AdminRoleCode::from($request->string('role')->toString());
        $active = $request->boolean('is_active');
        $demoted = $role !== $staff->roleCode() || ($staff->is_active && ! $active);

        if ($demoted && $staff->id === self::me($request)->id) {
            throw ValidationException::withMessages([$active ? 'role' : 'is_active' => Text::get('staff.self_guard')]);
        }
        if ($demoted && self::isLastActiveAdmin($staff)) {
            throw ValidationException::withMessages([$active ? 'role' : 'is_active' => Text::get('staff.last_admin')]);
        }

        $staff->fill([
            'name' => $request->string('name')->trim()->toString(),
            'admin_role_id' => self::roleId($role->value),
            'locale' => $request->string('locale')->toString(),
            'is_active' => $active,
        ])->save();
        if (! $active) {
            AdminPasswordLink::query()->where('admin_user_id', $staff->id)->whereNull('used_at')->delete();
        }

        return back()->with('status', Text::get('staff.saved'));
    }

    /** A reset link (or a new invite, for someone who never signed in). Not for a switched-off account. */
    public function sendLink(Request $request, AdminUser $staff, StaffPasswords $passwords): RedirectResponse
    {
        if (! $staff->is_active) {
            throw ValidationException::withMessages(['staff' => Text::get('staff.link_inactive')]);
        }
        $purpose = $staff->last_login_at === null ? AdminPasswordLink::INVITE : AdminPasswordLink::RESET;
        $passwords->sendLink($staff, $purpose, self::me($request));

        return back()->with('status', Text::get('staff.link_sent', ['email' => $staff->email]));
    }

    /** The event in words; an action with no wording yet shows its code. */
    private static function event(string $action): string
    {
        $key = 'staff.events.'.str_replace('.', '_', $action);
        $text = Text::get($key);

        return $text === 'dashboard.'.$key ? $action : $text;
    }

    private static function isLastActiveAdmin(AdminUser $staff): bool
    {
        if (! $staff->is_active || ! $staff->isAdmin()) {
            return false;
        }

        return AdminUser::query()->where('is_active', true)
            ->whereHas('role', static fn ($q) => $q->where('code', AdminRoleCode::Admin->value))
            ->count() <= 1;
    }

    private static function roleId(string $code): int
    {
        return AdminRole::query()->where('code', $code)->firstOrFail()->id;
    }

    private static function me(Request $request): AdminUser
    {
        $admin = $request->user('admin');
        if (! $admin instanceof AdminUser) {
            abort(403);
        }

        return $admin;
    }
}
