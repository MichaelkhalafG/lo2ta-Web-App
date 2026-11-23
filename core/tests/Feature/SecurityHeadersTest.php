<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/*
 * Audit M1 (no safety headers anywhere, PHP version shown) and M4 (session cookie not Secure in production).
 */

function assertSafetyHeaders(TestResponse $response): void
{
    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeaderMissing('X-Powered-By');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
}

it('sends the safety headers on the dashboard, the API and an API refusal', function () {
    assertSafetyHeaders($this->get('/dashboard/login'));
    assertSafetyHeaders($this->getJson('/api/v1/areas', apiHeaders()));
    assertSafetyHeaders($this->getJson('/api/v1/areas'));
    assertSafetyHeaders($this->get('/up'));
});

it('sends HSTS in production only', function () {
    $this->get('/dashboard/login')->assertHeaderMissing('Strict-Transport-Security');

    app()->detectEnvironment(fn () => 'production');
    $this->get('/dashboard/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('keeps the strict policy a file download sets for itself', function () {
    // ServeFile answers carry "default-src 'none' ... sandbox": the middleware must not replace it.
    $response = new Response('x', 200, ['Content-Security-Policy' => "default-src 'none'; sandbox"]);
    $out = (new SecurityHeaders)->handle(request(), fn () => $response);
    expect($out->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; sandbox")
        ->and($out->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('makes the session cookie Secure in production unless told otherwise (M4)', function () {
    $read = function (array $env): mixed {
        $saved = [];
        foreach ($env as $name => $value) {
            $saved[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
            if ($value === null) {
                unset($_SERVER[$name], $_ENV[$name]);
                putenv($name);
            } else {
                $_SERVER[$name] = $_ENV[$name] = $value;
                putenv("{$name}={$value}");
            }
        }
        $secure = (require config_path('session.php'))['secure'];
        foreach ($saved as $name => [$server, $envValue, $put]) {
            unset($_SERVER[$name], $_ENV[$name]);
            if ($server !== null) {
                $_SERVER[$name] = $server;
            }
            if ($envValue !== null) {
                $_ENV[$name] = $envValue;
            }
            putenv($put === false ? $name : "{$name}={$put}");
        }

        return $secure;
    };

    expect($read(['APP_ENV' => 'production', 'SESSION_SECURE_COOKIE' => null]))->toBeTrue()
        ->and($read(['APP_ENV' => 'local', 'SESSION_SECURE_COOKIE' => null]))->toBeFalse()
        ->and($read(['APP_ENV' => 'production', 'SESSION_SECURE_COOKIE' => 'false']))->toBeFalse();
});
