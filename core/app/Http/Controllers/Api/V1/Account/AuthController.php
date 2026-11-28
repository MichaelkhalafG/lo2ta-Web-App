<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Models\User;
use App\Support\Accounts\ClientIp;
use App\Support\Accounts\Links;
use App\Support\Accounts\LoginGuard;
use App\Support\Accounts\Phone;
use App\Support\Activity\ActivityLogger;
use App\Support\Mail\Outbox;
use App\Support\Text\PlainLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Public accounts (PLAN 2.1), called by the Next.js server with the server key. E-mail and password only; every
 * account e-mail goes through the outbox (no SMS, no WhatsApp). Rate limits use the visitor's IP forwarded by the
 * Next server (ClientIp). Messages answer in the language of ?locale=.
 */
class AuthController extends Controller
{
    public function register(Request $request, Outbox $outbox, ActivityLogger $log): JsonResponse
    {
        $ip = ClientIp::of($request);
        $this->limit("register:{$ip}", 5);

        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', PlainLine::RULE],
            'email' => ['required', 'string', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
            'terms' => ['accepted'],
            'website' => ['prohibited'],
        ]);
        $email = Str::lower(trim($request->string('email')->toString()));
        $phone = Phone::normalize($request->string('phone')->toString());
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('validation.custom.phone.phone')]);
        }
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => __('validation.attributes.email')])]);
        }
        RateLimiter::hit("register:{$ip}", 3600);

        $user = User::query()->create([
            'name' => trim($request->string('name')->toString()),
            'email' => $email,
            'phone' => $phone,
            'locale' => app()->getLocale(),
            'password' => $request->string('password')->toString(),
        ]);
        $log->record('user.registered', $user, null, ['email' => $user->email, 'locale' => $user->locale]);
        $this->sendVerification($user, $outbox);

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => new AccountResource($user),
        ], 201);
    }

    /**
     * Two limits: 5 tries a minute per e-mail and visitor address, and an account-wide ceiling across every address
     * (LoginGuard, audit H2).
     */
    public function login(Request $request, LoginGuard $guard): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'website' => ['prohibited'],
        ]);
        $email = Str::lower(trim($request->string('email')->toString()));
        $key = 'user-login:'.$email.'|'.ClientIp::of($request);
        $this->limit($key, 5);
        $guard->ensureOpen($email);

        $user = User::query()->where('email', $email)->first();
        if (! $user instanceof User || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($key, 60);
            $guard->failed($email, $user instanceof User ? $user : null);
            throw ValidationException::withMessages(['email' => self::text('accounts.login_failed')]);
        }
        RateLimiter::clear($key);
        $guard->clear($email);
        // G4: a suspended account cannot sign in (told only after the right password, so nothing leaks).
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['email' => self::text('accounts.suspended')]);
        }

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => new AccountResource($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            $user->currentAccessToken()->delete();
        }

        return response()->noContent();
    }

    /** Always the same answer, whether the e-mail exists or not (no account discovery). */
    public function forgotPassword(Request $request, Outbox $outbox): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:190'], 'website' => ['prohibited']]);
        $email = Str::lower(trim($request->string('email')->toString()));
        $this->limit('forgot:'.ClientIp::of($request), 10);
        RateLimiter::hit('forgot:'.ClientIp::of($request), 3600);

        $user = User::query()->where('email', $email)->first();
        $broker = Password::broker();
        if ($user instanceof User && ! $broker->getRepository()->recentlyCreatedToken($user)) {
            $token = $broker->createToken($user);
            $outbox->send($user->email, $user->name, $user->preferredLocale(), 'account.reset_password', [
                'kind' => 'reset_password',
                'name' => $user->name,
                'url' => Links::resetPassword($user, $token),
            ]);
        }

        return response()->json(['sent' => true]);
    }

    public function resetPassword(Request $request, ActivityLogger $log, LoginGuard $guard): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);
        $this->limit('reset:'.ClientIp::of($request), 10);

        $status = Password::broker()->reset(
            [
                'email' => Str::lower(trim($request->string('email')->toString())),
                'password' => $request->string('password')->toString(),
                'password_confirmation' => $request->string('password_confirmation')->toString(),
                'token' => $request->string('token')->toString(),
            ],
            static function (User $user, string $password) use ($log, $guard): void {
                $user->forceFill(['password' => $password])->save();
                // A new password signs the account out everywhere and lifts a sign-in lock.
                $user->tokens()->delete();
                $guard->clear($user->email, lift: true);
                $log->record('user.password_reset', $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            RateLimiter::hit('reset:'.ClientIp::of($request), 3600);
            throw ValidationException::withMessages(['email' => self::text('accounts.reset_invalid')]);
        }

        return response()->json(['reset' => true]);
    }

    public function verifyEmail(Request $request, ActivityLogger $log): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer'],
            'hash' => ['required', 'string', 'max:64'],
            'expires' => ['required', 'integer'],
            'signature' => ['required', 'string', 'max:128'],
        ]);
        $this->limit('verify:'.ClientIp::of($request), 20);
        RateLimiter::hit('verify:'.ClientIp::of($request), 60);

        $user = Links::verifiedUser(
            $request->integer('id'),
            $request->string('hash')->toString(),
            $request->integer('expires'),
            $request->string('signature')->toString(),
        );
        if (! $user instanceof User) {
            throw ValidationException::withMessages(['link' => self::text('accounts.verify_invalid')]);
        }
        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
            $log->record('user.email_verified', $user);
        }

        return response()->json(['verified' => true]);
    }

    public function resendVerification(Request $request, Outbox $outbox): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        if ($user->hasVerifiedEmail()) {
            return response()->json(['sent' => false, 'verified' => true]);
        }
        $this->limit('resend:'.$user->id, 1);
        RateLimiter::hit('resend:'.$user->id, 60);
        $this->sendVerification($user, $outbox);

        return response()->json(['sent' => true, 'verified' => false]);
    }

    private function sendVerification(User $user, Outbox $outbox): void
    {
        $outbox->send($user->email, $user->name, $user->preferredLocale(), 'account.verify_email', [
            'kind' => 'verify_email',
            'name' => $user->name,
            'url' => Links::verifyEmail($user),
        ]);
    }

    private function limit(string $key, int $max): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'email' => self::text('accounts.throttled', ['seconds' => RateLimiter::availableIn($key)]),
            ])->status(429);
        }
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        $value = __($key, $replace);

        return is_string($value) ? $value : $key;
    }
}
