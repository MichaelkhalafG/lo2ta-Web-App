<?php

namespace App\Support\Cache;

use Illuminate\Database\Eloquent\Model;

/**
 * Any write to the model invalidates the cache namespace(s) returned by cacheNamespaces(), so API answers built
 * from it are rebuilt on the next read.
 *
 * @mixin Model
 */
trait BumpsCacheOnWrite
{
    /**
     * @return list<string>
     */
    abstract public static function cacheNamespaces(): array;

    public static function bootBumpsCacheOnWrite(): void
    {
        $bump = static function (): void {
            foreach (static::cacheNamespaces() as $namespace) {
                VersionedCache::bump($namespace);
            }
        };

        static::saved($bump);
        static::deleted($bump);
    }
}
