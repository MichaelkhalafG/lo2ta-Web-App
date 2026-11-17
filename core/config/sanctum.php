<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

/*
| Public accounts (PLAN 2.1, RUN D10): the Next.js server signs a user in through the core API and keeps the user's
| token in an httpOnly cookie on the public domain. Tokens only: no stateful (cookie) domains and no fallback to the
| web session guard, so a dashboard session can never act as a public user.
*/

return [
    'stateful' => [],

    'guard' => [],

    // Minutes. A sign-in lasts 30 days; signing out deletes the token at once.
    'expiration' => 60 * 24 * 30,

    // A recognisable prefix so a leaked token is easy to find in logs and scanners.
    'token_prefix' => 'lk_',

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
