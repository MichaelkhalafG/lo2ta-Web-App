<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Enums\Readiness;
use App\Enums\RequestState;
use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use App\Models\SiteText;
use App\Models\Unit;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Accounts\ClientIp;
use App\Support\Activity\ActivityLogger;
use App\Support\Listing\UnitFacts;
use App\Support\Mail\Outbox;
use App\Support\Mail\TeamNotice;
use App\Support\Seller\Throttle;
use App\Support\Settings\Settings;
use App\Support\Text\ContactDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Request this unit" (PLAN 2.3): signed in with a confirmed e-mail (RUN D15: Q13 covers requests), a published unit,
 * at most 5 open requests per buyer (Q15) and one open request per unit, the 1% fee consent shown as the exact amount
 * (it must equal today's amount, so a consent never attaches to a stale figure), contact details in the question
 * masked. The buyer gets a reference by e-mail; the team gets a notice.
 */
class RequestsController extends Controller
{
    public const QUESTION_MAX = 500;

    public function index(Request $request): JsonResponse
    {
        $user = MeController::user($request);
        $locale = app()->getLocale();
        $rows = UnitRequest::query()->where('buyer_id', $user->id)->with('unit.area')->latest('id')->limit(100)->get();

        return response()->json(['data' => $rows->map(fn (UnitRequest $r): array => [
            'id' => $r->public_id,
            'reference' => self::reference($r),
            'state' => ['code' => $r->state->value, 'label' => $r->state->label()],
            'readiness' => ['code' => $r->readiness->value, 'label' => $r->readiness->label()],
            'fee_amount' => $r->fee_consent_amount,
            'created_at' => $r->created_at?->toIso8601String(),
            // Only what a buyer may see of the unit, and only while it is public (PLAN 2.5).
            'unit' => $r->unit instanceof Unit && $r->unit->state->isPublic()
                ? ['code' => $r->unit->code, 'slug' => $r->unit->slug, 'title' => UnitFacts::title($r->unit, $locale)]
                : ['code' => $r->unit?->code, 'slug' => null, 'title' => null],
        ])->values()->all()]);
    }

    public function store(Request $request, Settings $settings, Throttle $throttle, Outbox $outbox, TeamNotice $team, ActivityLogger $log): JsonResponse
    {
        $user = MeController::user($request);
        $ip = ClientIp::of($request);
        $throttle->hit('request', ['rl_request_user' => 'u:'.$user->id, 'rl_request_ip' => 'ip:'.$ip]);
        $request->validate([
            'code' => ['required', 'string', 'max:16'],
            'question' => ['nullable', 'string', 'max:'.self::QUESTION_MAX],
            'readiness' => ['required', Rule::enum(Readiness::class)],
            'fee_consent' => ['required', 'accepted'],
            'fee_amount' => ['required', 'integer', 'min:0'],
            'website' => ['nullable', 'max:0'],
        ]);
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => self::text('requests.email_not_verified')]);
        }

        $unit = Unit::query()->where('code', strtoupper(trim($request->string('code')->toString())))->where('state', UnitState::Published->value)->first();
        if (! $unit instanceof Unit) {
            throw ValidationException::withMessages(['code' => self::text('requests.unit_unavailable')]);
        }
        // A seller cannot ask for their own unit (audit L2).
        if ($unit->seller_id === $user->id) {
            throw ValidationException::withMessages(['code' => self::text('requests.own_unit')]);
        }
        $fee = UnitFacts::buyerFee($unit, $settings);
        if ($request->integer('fee_amount') !== $fee['amount']) {
            throw ValidationException::withMessages(['fee_consent' => self::text('requests.fee_changed')]);
        }

        $question = trim($request->string('question')->toString());
        $readiness = Readiness::from($request->string('readiness')->toString());
        $max = $settings->int('max_open_requests_per_buyer');
        // The buyer's row is locked while the open requests are counted and the new one is written, so two requests
        // sent at the same moment are taken one after the other and cannot both pass the checks (audit L9).
        $row = DB::transaction(function () use ($user, $unit, $max, $question, $readiness, $fee, $ip): UnitRequest {
            User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);
            $open = UnitRequest::query()->where('buyer_id', $user->id)->whereIn('state', RequestState::openValues());
            if ((clone $open)->where('unit_id', $unit->id)->exists()) {
                throw ValidationException::withMessages(['code' => self::text('requests.already_open')]);
            }
            if ($open->count() >= $max) {
                throw ValidationException::withMessages(['code' => self::text('requests.too_many', ['max' => $max])]);
            }

            return UnitRequest::query()->create([
                'unit_id' => $unit->id,
                'buyer_id' => $user->id,
                'question' => $question === '' ? null : ContactDetails::mask($question),
                'readiness' => $readiness,
                'fee_consent_amount' => $fee['amount'],
                'fee_consent_bp' => $fee['bp'],
                'fee_text_version' => self::feeTextVersion(),
                'fee_consent_at' => now(),
                'state' => RequestState::New,
                'ip' => $ip,
            ]);
        });
        $reference = self::reference($row);
        $log->record('unit_request.created', $unit, null, ['request' => $row->public_id, 'readiness' => $row->readiness->value]);
        $outbox->send($user->email, $user->name, $user->preferredLocale(), 'buyer.request_received', [
            'reference' => $reference, 'code' => (string) $unit->code, 'title' => UnitFacts::title($unit, $user->preferredLocale()),
            'fee' => number_format($fee['amount']),
        ]);
        $team->send('admin.unit_request', ['kind' => 'unit_request', 'reference' => $reference, 'code' => (string) $unit->code, 'readiness' => $row->readiness->value]);

        return response()->json(['data' => ['id' => $row->public_id, 'reference' => $reference]], 201);
    }

    /**
     * "R-8SMSXR · LK-NC-0124": six characters from the random end of the request's public id, with the unit code;
     * nothing about the buyer, and no running number that shows how many requests the site gets (audit L3).
     */
    public static function reference(UnitRequest $request): string
    {
        return 'R-'.strtoupper(substr((string) $request->public_id, -6)).($request->unit?->code !== null ? ' · '.$request->unit->code : '');
    }

    /** The fee consent wording is a client text (PLAN 6 row 9); its version is stored with each consent. */
    private static function feeTextVersion(): string
    {
        $text = SiteText::query()->where('key', 'buyer.fee_consent')->first(['ar', 'en']);

        return substr(hash('sha256', $text instanceof SiteText ? $text->ar."\n".$text->en : 'missing'), 0, 16);
    }

    /**
     * @param  array<string, int|string>  $params
     */
    private static function text(string $key, array $params = []): string
    {
        $text = __($key, $params);

        return is_string($text) ? $text : $key;
    }
}
