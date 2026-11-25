<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Cache\BumpsCacheOnWrite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One researched calculator value (config/market_values.php): when it was checked, its sources and whether the
 * client confirmed it. The numbers themselves are settings.
 *
 * @property string $key
 * @property Carbon $checked_on
 * @property Carbon|null $confirmed_at
 * @property int|null $confirmed_by
 * @property string|null $note
 * @property int|null $updated_by
 */
class MarketValue extends Model
{
    use BumpsCacheOnWrite, LogsActivity;

    protected $guarded = ['id'];

    /**
     * @return list<string>
     */
    public static function cacheNamespaces(): array
    {
        return ['tools'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_on' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MarketValueSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(MarketValueSource::class)->orderBy('sort')->orderBy('id');
    }
}
