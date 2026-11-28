<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\DeclarationKind;
use App\Enums\UnitState;
use App\Http\Controllers\Api\V1\Account\MeController;
use App\Http\Controllers\Controller;
use App\Http\Resources\SellerUnitResource;
use App\Models\ExitReport;
use App\Models\Unit;
use App\Support\Accounts\ClientIp;
use App\Support\Activity\ActivityLogger;
use App\Support\Calculator\ExitReportPdf;
use App\Support\Mail\Outbox;
use App\Support\Mail\TeamNotice;
use App\Support\Review\OpenDeals;
use App\Support\Seller\Declarations;
use App\Support\Seller\DuplicateGuard;
use App\Support\Seller\LiveEdits;
use App\Support\Seller\SellerUnits;
use App\Support\Seller\StepRules;
use App\Support\Seller\Throttle;
use App\Support\Uploads\PhotoPublisher;
use App\Support\Uploads\ServeFile;
use App\Support\Uploads\UploadService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The seller wizard (PLAN 2.2): drafts, the six steps with autosave, delete a draft, submit.
 * Every route takes the unit by its public ID and owner (SellerUnits), so another seller's unit answers 404.
 */
class UnitsController extends Controller
{
    /** Open drafts per account, against draft spam; finished units do not count. */
    private const MAX_OPEN_DRAFTS = 10;

    public function index(Request $request): JsonResponse
    {
        $user = MeController::user($request);
        $units = Unit::query()->where('seller_id', $user->id)->with('compound')->latest('updated_at')->get();

        return response()->json(['data' => $units->map(fn (Unit $u): array => [
            'id' => $u->public_id,
            'state' => ['code' => $u->state->value, 'label' => $u->state->label()],
            'reference' => $u->serial !== null ? sprintf('#%04d', $u->serial) : null,
            'code' => $u->code,
            'sub_type' => $u->sub_type?->label(),
            'compound' => $u->compound?->nameFor(app()->getLocale()) ?? $u->compound_other,
            'step_reached' => $u->step_reached,
            'updated_at' => $u->updated_at?->toIso8601String(),
        ])->values()->all()]);
    }

    /** A new draft. The no-over confirmation (PLAN 2.2.1) is required and stored per unit. */
    public function store(Request $request, Throttle $throttle, ActivityLogger $log): JsonResponse
    {
        $user = MeController::user($request);
        $request->validate(['no_over' => ['required', 'accepted']]);
        $throttle->hit('draft', ['rl_draft_user' => 'u:'.$user->id]);
        if (Unit::query()->where('seller_id', $user->id)->where('state', UnitState::Draft->value)->count() >= self::MAX_OPEN_DRAFTS) {
            throw ValidationException::withMessages(['no_over' => self::text('seller.too_many_drafts', ['max' => self::MAX_OPEN_DRAFTS])]);
        }

        $unit = DB::transaction(function () use ($user, $request): Unit {
            $unit = new Unit;
            $unit->forceFill(['seller_id' => $user->id, 'state' => UnitState::Draft, 'step_reached' => 1])->save();
            Declarations::record($unit, $user, DeclarationKind::NoOver, ClientIp::of($request));

            return $unit;
        });
        $log->record('unit.draft_created', $unit);

        return (new SellerUnitResource($unit->refresh()))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $unit): SellerUnitResource
    {
        return new SellerUnitResource(SellerUnits::unit(MeController::user($request), $unit));
    }

    /**
     * Autosave a step; with complete=true, also check the step ("Next") and move on.
     * The data is saved either way, so nothing typed is lost when a check fails.
     */
    public function saveStep(Request $request, string $unit, int $step, StepRules $rules, Throttle $throttle, DuplicateGuard $duplicates): JsonResponse
    {
        $user = MeController::user($request);
        $model = SellerUnits::unit($user, $unit);
        $throttle->hit('draft', ['rl_draft_user' => 'u:'.$user->id]);
        self::mustBeEditable($model);
        if (! in_array($step, [1, 2, 3, 4, 5], true)) {
            throw ValidationException::withMessages(['step' => self::text('seller.rules.step')]);
        }
        $complete = $request->boolean('complete');

        $input = [];
        foreach ($request->except('complete') as $key => $value) {
            if (is_string($key)) {
                $input[$key] = $value;
            }
        }
        $features = $step === 4 ? null : $rules->apply($step, $model, $input);
        DB::transaction(function () use ($model, $features): void {
            $model->save();
            if ($features !== null) {
                $model->features()->sync($features);
            }
        });

        if (! $complete) {
            return (new SellerUnitResource($model->refresh()))->response();
        }

        $problems = $rules->problems($step, $model);
        if ($problems !== []) {
            throw ValidationException::withMessages($problems);
        }
        if ($step === 1) {
            $throttle->hit('dupcheck', ['rl_dupcheck_user' => 'u:'.$user->id]);
            $duplicates->check($model, $user, 'step1');
        }
        $model->forceFill(['step_reached' => max($model->step_reached, $step + 1)])->save();

        return (new SellerUnitResource($model->refresh()))->response();
    }

    /** Only a draft that was never submitted can be deleted; its files go with it. */
    public function destroy(Request $request, string $unit, ActivityLogger $log): JsonResponse
    {
        $model = SellerUnits::unit(MeController::user($request), $unit);
        if ($model->state !== UnitState::Draft || $model->serial !== null) {
            throw ValidationException::withMessages(['unit' => self::text('seller.not_deletable')]);
        }
        $photos = $model->photos()->get();
        $documents = $model->documents()->get();
        $log->record('unit.draft_deleted', $model);
        $model->delete();
        foreach ($photos as $photo) {
            UploadService::deletePhotoFiles($photo);
        }
        foreach ($documents as $document) {
            if (is_string($document->path)) {
                Storage::disk(UploadService::PRIVATE_DISK)->delete($document->path);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Submit (step 6). Needs a confirmed e-mail (Q13), every step complete on the stored data, the three declarations
     * (accepted again on every submit, PLAN 2.2.14) and no duplicate contract.
     */
    public function submit(Request $request, string $unit, StepRules $rules, Throttle $throttle, DuplicateGuard $duplicates, Outbox $outbox, TeamNotice $team, ActivityLogger $log, ExitReportPdf $pdfs): JsonResponse
    {
        $user = MeController::user($request);
        $model = SellerUnits::unit($user, $unit);
        $throttle->hit('submit', ['rl_submit_user' => 'u:'.$user->id, 'rl_submit_ip' => 'ip:'.ClientIp::of($request)]);
        self::mustBeEditable($model);
        $request->validate([
            'declarations' => ['required', 'array'],
            'declarations.owner_not_broker' => ['required', 'accepted'],
            'declarations.data_matches' => ['required', 'accepted'],
            'declarations.terms_privacy' => ['required', 'accepted'],
        ]);
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => self::text('seller.email_not_verified')]);
        }

        StepRules::normalize($model);
        $problems = [];
        if (! $model->declarations()->where('kind', DeclarationKind::NoOver->value)->exists()) {
            $problems['no_over'] = self::text('seller.rules.required');
        }
        foreach ([1, 2, 3, 4, 5] as $step) {
            foreach ($rules->problems($step, $model) as $field => $message) {
                $problems["steps.{$step}.{$field}"] = $message;
            }
        }
        if ($problems !== []) {
            throw ValidationException::withMessages($problems);
        }
        $duplicates->check($model, $user, 'submit');

        $first = $model->serial === null;
        $resubmitted = $model->state === UnitState::ChangesRequested;
        try {
            DB::transaction(function () use ($model, $user, $request): void {
                if ($model->serial === null) {
                    $max = Unit::query()->lockForUpdate()->max('serial');
                    $model->serial = (is_numeric($max) ? max(0, (int) $max) : 0) + 1;
                }
                $model->forceFill([
                    'state' => UnitState::Submitted,
                    'step_reached' => 6,
                    'submitted_at' => now(),
                    'active_contract_key' => $model->contract_key,
                    'active_unit_key' => $model->unit_key,
                ])->save();
                foreach (Declarations::SUBMIT as $kind) {
                    Declarations::record($model, $user, $kind, ClientIp::of($request));
                }
            });
        } catch (QueryException $e) {
            // Two sellers submitting the same contract at the same moment: the unique keys decide (PLAN 3.4).
            if (($e->errorInfo[0] ?? null) === '23000') {
                throw ValidationException::withMessages(['contract_number' => DuplicateGuard::message()]);
            }

            throw $e;
        }

        $reference = sprintf('#%04d', $model->serial);
        $log->record($resubmitted ? 'unit.resubmitted' : 'unit.submitted', $model, null, ['reference' => $reference]);

        // The exit report "as declared", in both languages (PLAN 4.4, Q8). A PDF failure never blocks the submit.
        $attach = null;
        foreach (['ar', 'en'] as $language) {
            try {
                $report = $pdfs->generate($model, 'declared', $language);
                if ($report !== null && $language === $user->preferredLocale()) {
                    $attach = $report->id;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
        $outbox->send($user->email, $user->name, $user->preferredLocale(), 'seller.unit_received', ['reference' => $reference, 'exit_report_id' => $attach]);
        $team->send('admin.unit_submitted', ['reference' => $reference, 'resubmitted' => $resubmitted ? '1' : '0', 'first' => $first ? '1' : '0']);

        return (new SellerUnitResource($model->refresh()))->response();
    }

    /** Edit a published or reserved unit (PLAN 2.2.7 tiers): free fields go live, review fields wait for the admin. */
    public function liveEdit(Request $request, string $unit, LiveEdits $edits, Throttle $throttle): JsonResponse
    {
        $user = MeController::user($request);
        $model = SellerUnits::unit($user, $unit);
        $throttle->hit('draft', ['rl_draft_user' => 'u:'.$user->id]);
        if (! in_array($model->state, [UnitState::Published, UnitState::Reserved], true)) {
            throw ValidationException::withMessages(['unit' => self::text('seller.not_editable')]);
        }
        $input = [];
        foreach ($request->all() as $key => $value) {
            if (is_string($key)) {
                $input[$key] = $value;
            }
        }
        $result = $edits->apply($model, $user, $input);

        return (new SellerUnitResource($model->refresh()))->additional(['meta' => ['live' => $result['live'], 'pending' => $result['pending'] !== null]])->response();
    }

    /**
     * Withdraw (PLAN 2.2.7, 2.2.9 point 5). A reserved unit has an open deal: the seller's request goes to the admin
     * instead. Otherwise the unit leaves the market at once, its keys are released and its public photos removed.
     */
    public function withdraw(Request $request, string $unit, Throttle $throttle, Outbox $outbox, TeamNotice $team, ActivityLogger $log, PhotoPublisher $photos): JsonResponse
    {
        $user = MeController::user($request);
        $model = SellerUnits::unit($user, $unit);
        $throttle->hit('submit', ['rl_submit_user' => 'u:'.$user->id, 'rl_submit_ip' => 'ip:'.ClientIp::of($request)]);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $reason = trim((string) $request->string('reason')) ?: null;
        $reference = $model->code ?? ($model->serial !== null ? sprintf('#%04d', $model->serial) : '');

        // A deal is open (the unit is reserved, or an earlier stage of a deal, part 7.5): a request to the team.
        if ($model->state === UnitState::Reserved || OpenDeals::has($model)) {
            if ($model->withdraw_requested_at === null) {
                $model->forceFill(['withdraw_requested_at' => now(), 'withdraw_reason' => $reason])->save();
                $log->record('unit.withdraw_requested', $model, null, ['reason' => $reason]);
                $team->send('admin.unit_submitted', ['reference' => $reference, 'kind' => 'withdraw_request']);
            }

            return (new SellerUnitResource($model->refresh()))->response();
        }
        if (! in_array($model->state, [UnitState::Submitted, UnitState::ChangesRequested, UnitState::Published], true)) {
            throw ValidationException::withMessages(['unit' => self::text('seller.not_withdrawable')]);
        }

        DB::transaction(function () use ($model, $reason): void {
            $model->forceFill([
                'state' => UnitState::Withdrawn,
                'withdrawn_at' => now(),
                'withdraw_reason' => $reason,
                'active_contract_key' => null,
                'active_unit_key' => null,
            ])->save();
            $model->pendingChanges()->where('state', 'pending')->update(['state' => 'rejected', 'note' => 'withdrawn by the seller', 'reviewed_at' => now()]);
        });
        foreach ($model->photos()->get() as $photo) {
            $photos->withdraw($photo);
        }
        $log->record('unit.withdrawn', $model, null, ['reason' => $reason]);
        $outbox->send($user->email, $user->name, $user->preferredLocale(), 'seller.unit_withdrawn', ['reference' => $reference]);
        $team->send('admin.unit_submitted', ['reference' => $reference, 'kind' => 'withdrawn']);

        return (new SellerUnitResource($model->refresh()))->response();
    }

    /** The latest exit report PDF in the asked language: after review when there is one, else as declared. */
    public function exitReport(Request $request, string $unit): StreamedResponse
    {
        $model = SellerUnits::unit(MeController::user($request), $unit);
        $language = $request->query('locale') === 'en' ? 'en' : 'ar';
        $report = $model->exitReports()->where('locale', $language)->whereNotNull('pdf_path')
            ->orderByRaw("kind = 'verified' desc")->latest('generated_at')->latest('id')->first();
        if (! $report instanceof ExitReport || ! is_string($report->pdf_path)) {
            abort(404);
        }
        $name = 'exit-report-'.($model->serial !== null ? sprintf('%04d', $model->serial) : 'draft').'-'.$language.'.pdf';

        return ServeFile::private($report->pdf_path, 'application/pdf', $name);
    }

    public static function mustBeEditable(Unit $unit): void
    {
        if (! $unit->isEditableBySeller()) {
            throw ValidationException::withMessages(['unit' => self::text('seller.not_editable')]);
        }
    }

    /**
     * @param  array<string, int|string>  $params
     */
    public static function text(string $key, array $params = []): string
    {
        $text = __($key, $params);

        return is_string($text) ? $text : $key;
    }
}
