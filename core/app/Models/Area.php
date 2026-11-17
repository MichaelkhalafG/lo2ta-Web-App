<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Cache\BumpsCacheOnWrite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Two levels (PLAN 3.3): a governorate (parent_id null, no code) and its areas (with the code used in unit codes).
 */
class Area extends Model
{
    use BumpsCacheOnWrite, LogsActivity;

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
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }

    /**
     * @return HasMany<Area, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Area::class, 'parent_id');
    }

    /**
     * @param  Builder<Area>  $query
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
