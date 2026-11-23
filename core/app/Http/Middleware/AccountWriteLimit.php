<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Seller\Throttle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An hourly ceiling on a signed-in user's account writes: profile, favorites and budget (audit L4, setting
 * rl_account_write_user). Far above what a person does, low enough that a stolen token cannot hammer the database.
 * Over the limit: 429 with Retry-After (Throttle).
 */
final class AccountWriteLimit
{
    public function __construct(private readonly Throttle $throttle) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->throttle->hit('account-write', ['rl_account_write_user' => 'u:'.$user->id]);
        }

        return $next($request);
    }
}
