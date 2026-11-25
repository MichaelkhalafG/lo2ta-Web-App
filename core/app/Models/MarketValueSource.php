<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Cache\BumpsCacheOnWrite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A source behind a market value. Internal only (J10): shown in the dashboard and the findings table, never on the
 * public site.
 *
 * @property int $market_value_id
 * @property string $ref
 * @property string $publisher
 * @property string|null $url
 * @property Carbon|null $published_on
 * @property Carbon $read_on
 * @property string $figure
 * @property string|null $origin
 * @property bool $counted
 * @property int $sort
 */
class MarketValueSource extends Model
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
            'published_on' => 'date',
            'read_on' => 'date',
            'counted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MarketValue, $this>
     */
    public function marketValue(): BelongsTo
    {
        return $this->belongsTo(MarketValue::class);
    }
}
