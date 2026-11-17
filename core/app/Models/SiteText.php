<?php

namespace App\Models;

use App\Enums\TextSource;
use App\Support\Activity\LogsActivity;
use App\Support\Cache\BumpsCacheOnWrite;
use Illuminate\Database\Eloquent\Model;

/**
 * A text the client supplies (agreement clause 7). While ours is a placeholder, is_placeholder is true and the
 * dashboard lists it.
 */
class SiteText extends Model
{
    use BumpsCacheOnWrite, LogsActivity;

    protected $guarded = ['id'];

    /**
     * @return list<string>
     */
    public static function cacheNamespaces(): array
    {
        return ['site_texts'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_placeholder' => 'boolean',
            'source' => TextSource::class,
        ];
    }

    public function textFor(string $locale): ?string
    {
        $value = $locale === 'en' ? $this->en : $this->ar;

        return is_string($value) ? $value : null;
    }
}
