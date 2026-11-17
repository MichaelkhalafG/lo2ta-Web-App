<?php

namespace App\Models;

use App\Enums\FeatureGroup;
use App\Support\Activity\LogsActivity;
use App\Support\Cache\BumpsCacheOnWrite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A unit feature (PLAN 2.2.5). Kept in a table so the admin can add or hide one.
 */
class Feature extends Model
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
            'group' => FeatureGroup::class,
            'applies_to' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<Feature>  $query
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
