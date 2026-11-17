<?php

namespace App\Support\Accounts;

use Illuminate\Http\Request;

/**
 * The visitor's IP address for rate limits. Account calls reach core from the Next.js server, so the request IP is
 * the web server's; the Next server forwards the visitor's address in X-Client-Ip. The header is trusted only on
 * requests that passed the server key (RequireServerKey runs first on every keyed route).
 */
final class ClientIp
{
    public static function of(Request $request): string
    {
        $forwarded = $request->header('X-Client-Ip');
        if (is_string($forwarded) && filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
            return $forwarded;
        }

        return (string) $request->ip();
    }
}
