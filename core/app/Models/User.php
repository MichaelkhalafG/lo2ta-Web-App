<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A public account (seller, buyer or both; PLAN 2.1). Staff are AdminUser, never a row here. The Next.js server
 * acts for the user with a Sanctum token kept in an httpOnly cookie (RUN D10).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'locale', 'password'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'seller_id');
    }

    /**
     * @return BelongsToMany<Unit, $this>
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'favorites')->withPivot('created_at');
    }

    /**
     * @return HasOne<BuyerBudget, $this>
     */
    public function budget(): HasOne
    {
        return $this->hasOne(BuyerBudget::class);
    }

    /**
     * The person's unit requests as a buyer (PLAN 2.3).
     *
     * @return HasMany<UnitRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(UnitRequest::class, 'buyer_id');
    }

    /** Suspended by an admin (G4): no sign-in, units held off the site. */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function preferredLocale(): string
    {
        return in_array($this->locale, ['ar', 'en'], true) ? $this->locale : 'ar';
    }
}
