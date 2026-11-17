<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Cache\BumpsCacheOnWrite;
use Database\Factories\DeveloperFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Developer extends Model
{
    /** @use HasFactory<DeveloperFactory> */
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
        ];
    }

    /**
     * @return HasMany<Compound, $this>
     */
    public function compounds(): HasMany
    {
        return $this->hasMany(Compound::class);
    }

    /**
     * @param  Builder<Developer>  $query
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
