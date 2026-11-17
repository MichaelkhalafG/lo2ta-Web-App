<?php

namespace App\Models;

use App\Enums\AdminRole as AdminRoleCode;
use App\Support\Activity\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminRole extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => AdminRoleCode::class,
        ];
    }

    /**
     * @return HasMany<AdminUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(AdminUser::class);
    }
}
