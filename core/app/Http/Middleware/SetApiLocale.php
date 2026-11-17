<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public site states the language with ?locale=ar|en; anything else falls back to Arabic.
 * The answer varies by locale, so it says so for any cache in between.
 */
class SetApiLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys((array) config('locales.supported'));
        $asked = $request->query('locale');
        $default = config('locales.default');

        app()->setLocale(is_string($asked) && in_array($asked, $supported, true)
            ? $asked
            : (is_string($default) ? $default : 'ar'));

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
