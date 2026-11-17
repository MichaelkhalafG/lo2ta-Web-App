<?php

namespace App\Support\Dashboard;

/**
 * A dashboard string from lang/{locale}/dashboard.php, by dotted key, for server code (exports, flash messages).
 * A missing key returns the key, so a gap shows instead of disappearing.
 */
final class Text
{
    /**
     * @param  array<string, string|int>  $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $value = __('dashboard.'.$key, $replace);

        return is_string($value) ? $value : $key;
    }
}
