<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\PartnershipRequest;
use App\Support\Accounts\ClientIp;
use App\Support\Accounts\Phone;
use App\Support\Mail\TeamNotice;
use App\Support\Seller\Throttle;
use App\Support\Settings\Settings;
use App\Support\Text\Digits;
use App\Support\Text\PlainLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The two public forms (PLAN 1.2; Michael 2026-11-22: minimal fields, no automatic e-mail to the sender): contact and
 * developer partnership. Honeypot (Q18), hourly limits per IP (settings rl_contact_ip, rl_partnership_ip), message
 * length (form_message_max). The team gets the message by e-mail until the phase 7 inbox exists (PLAN D25).
 * A filled honeypot gets the same "thank you" as a person, and nothing is stored.
 */
class FormsController extends Controller
{
    public function contact(Request $request, Settings $settings, Throttle $throttle, TeamNotice $team): JsonResponse
    {
        $throttle->hit('contact', ['rl_contact_ip' => 'ip:'.ClientIp::of($request)]);
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', PlainLine::RULE],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:'.$settings->int('form_message_max')],
            'website' => ['nullable', 'string', 'max:200'],
        ]);
        if (self::isBot($request)) {
            return response()->json(['ok' => true], 201);
        }
        $text = self::reader($request);
        $phoneTyped = $text('phone');
        $phone = $phoneTyped === '' ? null : Phone::normalize($phoneTyped);
        if ($phoneTyped !== '' && $phone === null) {
            throw ValidationException::withMessages(['phone' => self::text('validation.custom.phone.phone')]);
        }

        $fields = ['name' => $text('name'), 'email' => strtolower($text('email')), 'phone' => $phone, 'message' => $text('message')];
        ContactMessage::query()->create([...$fields, 'ip' => ClientIp::of($request)]);
        $team->send('admin.contact_message', ['kind' => 'contact_message', ...array_map(fn (?string $v): string => (string) $v, $fields)]);

        return response()->json(['ok' => true], 201);
    }

    public function partnership(Request $request, Settings $settings, Throttle $throttle, TeamNotice $team): JsonResponse
    {
        $throttle->hit('partnership', ['rl_partnership_ip' => 'ip:'.ClientIp::of($request)]);
        $request->validate([
            'company' => ['required', 'string', 'min:2', 'max:160', PlainLine::RULE],
            'contact_name' => ['required', 'string', 'min:2', 'max:120', PlainLine::RULE],
            'job_title' => ['nullable', 'string', 'max:120', PlainLine::RULE],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'projects_count' => ['nullable', 'integer', 'between:0,1000'],
            'message' => ['required', 'string', 'min:10', 'max:'.$settings->int('form_message_max')],
            'website' => ['nullable', 'string', 'max:200'],
        ]);
        if (self::isBot($request)) {
            return response()->json(['ok' => true], 201);
        }
        $text = self::reader($request);
        $phone = self::anyPhone($text('phone'));
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => self::text('validation.custom.phone.phone')]);
        }

        $fields = [
            'company' => $text('company'),
            'contact_name' => $text('contact_name'),
            'job_title' => $text('job_title') === '' ? null : $text('job_title'),
            'email' => strtolower($text('email')),
            'phone' => $phone,
            'projects_count' => $request->filled('projects_count') ? $request->integer('projects_count') : null,
            'message' => $text('message'),
        ];
        PartnershipRequest::query()->create([...$fields, 'ip' => ClientIp::of($request)]);
        $team->send('admin.partnership_request', ['kind' => 'partnership_request', ...array_map(fn (int|string|null $v): string => (string) $v, $fields)]);

        return response()->json(['ok' => true], 201);
    }

    /**
     * @return callable(string): string trimmed text of a validated field ('' when absent)
     */
    private static function reader(Request $request): callable
    {
        return static fn (string $key): string => trim($request->string($key)->toString());
    }

    private static function isBot(Request $request): bool
    {
        return trim($request->string('website')->toString()) !== '';
    }

    /**
     * A company may give a landline or a number abroad (PLAN D27): an Egyptian mobile in its usual form, else 7 to
     * 15 digits with an optional leading +.
     */
    private static function anyPhone(string $value): ?string
    {
        $mobile = Phone::normalize($value);
        if ($mobile !== null) {
            return $mobile;
        }
        $latin = Digits::latin($value);
        $plus = str_starts_with(ltrim($latin), '+') ? '+' : '';
        $digits = (string) preg_replace('/[^0-9]/', '', $latin);

        return strlen($digits) >= 7 && strlen($digits) <= 15 ? $plus.$digits : null;
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
