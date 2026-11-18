<?php

namespace App\Support\Seller;

use App\Support\Settings\Settings;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Hourly limits per user and per IP for the seller wizard (Michael 2026-11-18, settings rl_*). Every limit of a call
 * is checked before any of them counts, so a refused call costs nothing. Over a limit: 429 with Retry-After.
 */
final class Throttle
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array<string, string>  $limits  setting key => who (e.g. 'rl_upload_user' => 'u:12')
     */
    public function hit(string $action, array $limits): void
    {
        foreach ($limits as $setting => $who) {
            $key = "seller:{$action}:{$who}";
            if (RateLimiter::tooManyAttempts($key, $this->settings->int($setting))) {
                $seconds = RateLimiter::availableIn($key);
                $text = __('seller.throttled', ['minutes' => (int) ceil($seconds / 60)]);

                throw new HttpResponseException(response()->json([
                    'message' => is_string($text) ? $text : 'seller.throttled',
                    'retry_after' => $seconds,
                ], 429, ['Retry-After' => (string) $seconds]));
            }
        }
        foreach ($limits as $who) {
            RateLimiter::hit("seller:{$action}:{$who}", 3600);
        }
    }
}
