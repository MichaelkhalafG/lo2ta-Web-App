<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Cache\BumpsCacheOnWrite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A figure the client supplies for the reports page (PLAN 6 row 14, J9). The source is internal (J10).
 *
 * @property string $title_ar
 * @property string $title_en
 * @property string $value
 * @property string $unit
 * @property string|null $note_ar
 * @property string|null $note_en
 * @property Carbon $as_of
 * @property string $source
 * @property int $sort
 * @property bool $is_published
 */
class ReportIndicator extends Model
{
    use BumpsCacheOnWrite, LogsActivity;

    public const UNITS = ['pct', 'egp', 'count', 'months', 'years'];

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
            'value' => 'decimal:2',
            'as_of' => 'date',
            'is_published' => 'boolean',
        ];
    }
}
