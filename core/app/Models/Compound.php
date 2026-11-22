<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Cache\BumpsCacheOnWrite;
use Database\Factories\CompoundFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compound extends Model
{
    /** @use HasFactory<CompoundFactory> */
    use BumpsCacheOnWrite, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    /**
     * @return list<string>
     */
    public static function cacheNamespaces(): array
    {
        return ['places'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'intro_is_placeholder' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Developer, $this>
     */
    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * @param  Builder<Compound>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function nameFor(string $locale): string
    {
        return $locale === 'en' ? $this->name_en : $this->name_ar;
    }
}
