<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side reads from the Next.js site carry X-Api-Key (handover 2.2). Fails closed: with no key configured,
 * every keyed call is refused. 401 also tells a live probe that the request reached Laravel (handover 2.4).
 */
class RequireServerKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.web.server_key');
        $given = $request->header('X-Api-Key');

        if (! is_string($expected) || $expected === '' || ! is_string($given) || ! hash_equals($expected, $given)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
