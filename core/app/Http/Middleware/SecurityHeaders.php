<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser safety headers on every core answer: the dashboard, the API and file downloads (audit M1, Michael
 * 2026-11-23 decision 7A). No page of core may be framed by another site, types are never guessed, the address of
 * a page is not sent to other sites, and the browser features the dashboard never uses stay off. HSTS only in
 * production, where core is served over HTTPS. A header a controller already set (a download's own
 * Content-Security-Policy) is kept. The full Content-Security-Policy comes in Phase 8.
 */
final class SecurityHeaders
{
    /** @var array<string, string> */
    private const HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        }
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        // PHP's own version banner (expose_php is also switched off on the server, DEPLOY_NOTES).
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
