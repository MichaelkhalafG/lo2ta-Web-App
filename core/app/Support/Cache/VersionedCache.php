<?php

namespace App\Support\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * File cache with version-key invalidation (handover 2.2: no Redis on shared hosting).
 * Each namespace has a version number; bumping it makes every older entry unreachable at once.
 */
final class VersionedCache
{
    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public static function remember(string $namespace, string $key, Closure $build): mixed
    {
        return Cache::rememberForever(self::key($namespace, $key), $build);
    }

    public static function bump(string $namespace): void
    {
        Cache::forever(self::versionKey($namespace), self::version($namespace) + 1);
    }

    public static function version(string $namespace): int
    {
        $version = Cache::get(self::versionKey($namespace), 1);

        return is_int($version) ? $version : 1;
    }

    private static function key(string $namespace, string $key): string
    {
        return $namespace.':v'.self::version($namespace).':'.$key;
    }

    private static function versionKey(string $namespace): string
    {
        return $namespace.':version';
    }
}
