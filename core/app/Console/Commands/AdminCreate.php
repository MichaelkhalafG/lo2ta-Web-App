<?php

namespace App\Console\Commands;

use App\Enums\AdminRole as AdminRoleCode;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates a dashboard account. The password is typed by Michael at a hidden prompt: it never goes into a
 * file, a script, the shell history or the chat.
 */
class AdminCreate extends Command
{
    protected $signature = 'admin:create {email} {name} {--role=reviewer : admin or reviewer} {--locale=ar : ar or en}';

    protected $description = 'Create a dashboard account (asks for the password at a hidden prompt)';

    public function handle(): int
    {
        $data = [
            'email' => $this->argument('email'),
            'name' => $this->argument('name'),
            'role' => $this->option('role'),
            'locale' => $this->option('locale'),
        ];

        $check = Validator::make($data, [
            'email' => ['required', 'email', 'unique:admin_users,email'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'role' => ['required', 'in:'.implode(',', AdminRoleCode::values())],
            'locale' => ['required', 'in:ar,en'],
        ]);
        if ($check->fails()) {
            $this->error('STOP - '.implode(' ', $check->errors()->all()));

            return self::FAILURE;
        }

        $role = AdminRole::query()->where('code', $data['role'])->first();
        if (! $role instanceof AdminRole) {
            $this->error('STOP - roles are not seeded; run php artisan db:seed --class=ReferenceSeeder first');

            return self::FAILURE;
        }

        $password = $this->secret('Password (at least 12 characters)');
        $again = $this->secret('Password again');
        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->error('STOP - the password needs at least 12 characters');

            return self::FAILURE;
        }
        if ($password !== $again) {
            $this->error('STOP - the two passwords differ');

            return self::FAILURE;
        }

        $admin = AdminUser::query()->create([
            'admin_role_id' => $role->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $password,
            'locale' => $data['locale'],
            'is_active' => true,
        ]);

        $this->info("CREATED - admin user {$admin->id} ({$role->code->value})");

        return self::SUCCESS;
    }
}
