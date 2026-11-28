<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-use set-password link for a staff member (G13, G14). Not logged by itself: the token hash must never reach
 * the activity log; StaffPasswords writes its own log lines (who sent it, to whom, which purpose).
 *
 * @property int $id
 * @property int $admin_user_id
 * @property string $purpose
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property int|null $created_by
 */
class AdminPasswordLink extends Model
{
    public const INVITE = 'invite';

    public const RESET = 'reset';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
