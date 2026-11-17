<?php

namespace App\Support\Accounts;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Links in account e-mails point to the PUBLIC site (config app.frontend_url), in the user's language: Arabic at
 * the root, English under /en (PLAN 1.1). The verification link carries an HMAC signature over the user id, a hash
 * of the e-mail and the expiry, checked by core when the public site posts it back.
 */
final class Links
{
    public const VERIFY_HOURS = 24;

    public static function frontend(string $path, string $locale): string
    {
        $base = config('app.frontend_url');
        $base = is_string($base) ? $base : '';

        return $base.($locale === 'en' ? '/en' : '').$path;
    }

    public static function verifyEmail(User $user): string
    {
        $expires = Carbon::now()->addHours(self::VERIFY_HOURS)->getTimestamp();
        $hash = sha1($user->email);
        $query = http_build_query([
            'id' => $user->id,
            'hash' => $hash,
            'expires' => $expires,
            'signature' => self::signature($user->id, $hash, $expires),
        ]);

        return self::frontend('/verify-email?'.$query, $user->preferredLocale());
    }

    /**
     * The user the link belongs to, or null when the link is wrong, tampered with or expired.
     */
    public static function verifiedUser(int $id, string $hash, int $expires, string $signature): ?User
    {
        if ($expires < Carbon::now()->getTimestamp()) {
            return null;
        }
        if (! hash_equals(self::signature($id, $hash, $expires), $signature)) {
            return null;
        }
        $user = User::query()->find($id);

        return $user instanceof User && hash_equals(sha1($user->email), $hash) ? $user : null;
    }

    public static function resetPassword(User $user, string $token): string
    {
        return self::frontend('/reset-password/'.rawurlencode($token).'?'.http_build_query(['email' => $user->email]), $user->preferredLocale());
    }

    private static function signature(int $id, string $hash, int $expires): string
    {
        $key = config('app.key');

        return hash_hmac('sha256', $id.'|'.$hash.'|'.$expires, is_string($key) ? $key : '');
    }
}
