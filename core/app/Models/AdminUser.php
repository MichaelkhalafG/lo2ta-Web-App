<?php

namespace App\Models;

use App\Enums\AdminRole as AdminRoleCode;
use App\Support\Activity\LogsActivity;
use Database\Factories\AdminUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Dashboard staff (guard "admin"). Never a row in `users` (handover 2.3).
 */
class AdminUser extends Authenticatable
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['admin_role_id', 'name', 'email', 'password', 'locale', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<AdminRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }

    public function roleCode(): AdminRoleCode
    {
        $role = $this->loadMissing('role')->role;

        return $role instanceof AdminRole ? $role->code : AdminRoleCode::Reviewer;
    }

    public function isAdmin(): bool
    {
        return $this->roleCode() === AdminRoleCode::Admin;
    }
}
