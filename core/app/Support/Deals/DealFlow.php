<?php

namespace App\Support\Deals;

use App\Enums\CommissionStatus;
use App\Enums\DealStage;
use App\Enums\RequestState;
use App\Enums\UnitState;
use App\Models\AdminUser;
use App\Models\Commission;
use App\Models\Deal;
use App\Models\Unit;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Accounts\Links;
use App\Support\Activity\ActivityLogger;
use App\Support\Dashboard\Text;
use App\Support\Listing\UnitFacts;
use App\Support\Mail\Outbox;
use App\Support\Settings\Settings;
use App\Support\Uploads\PhotoPublisher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deals (PLAN 2.4, Q16; part 7.5; G7, G11, G12, G18). One open deal per unit, other buyers wait as requests. A deal
 * moves forward one stage at a time or back one with a note; it is cancelled from any open stage with a reason;
 * completed is final. The unit is reserved from the seller's acceptance, sold at completion, and back on the market
 * when a deal is cancelled. Both parties are e-mailed at opened, developer approval obtained, completed and
 * cancelled; the buyer's e-mails never carry seller details (PLAN 2.5), the seller's never the buyer's.
 */
final class DealFlow
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly Outbox $outbox,
        private readonly Settings $settings,
        private readonly PhotoPublisher $photos,
    ) {}

    /** Open a deal on a published unit for a buyer, from their request when there is one. */
    public function open(Unit $unit, User $buyer, ?UnitRequest $request, AdminUser $by): Deal
    {
        if ($unit->state !== UnitState::Published || $unit->held_at !== null) {
            throw ValidationException::withMessages(['unit' => Text::get('deals.errors.unit_state')]);
        }
        if ($buyer->id === $unit->seller_id || $buyer->isSuspended()) {
            throw ValidationException::withMessages(['buyer' => Text::get('deals.errors.buyer')]);
        }
        if ($request !== null && ($request->unit_id !== $unit->id || $request->buyer_id !== $buyer->id || ! in_array($request->state->value, RequestState::openValues(), true))) {
            throw ValidationException::withMessages(['request' => Text::get('deals.errors.request')]);
        }

        try {
            $deal = DB::transaction(function () use ($unit, $buyer, $request, $by): Deal {
                Unit::query()->whereKey($unit->id)->lockForUpdate()->first();
                $deal = new Deal;
                $deal->forceFill([
                    'unit_id' => $unit->id, 'open_unit_id' => $unit->id, 'buyer_id' => $buyer->id, 'seller_id' => $unit->seller_id,
                    'unit_request_id' => $request?->id, 'stage' => DealStage::Opened, 'opened_by' => $by->id, 'opened_at' => now(),
                ])->save();
                $deal->events()->create(['from' => null, 'to' => DealStage::Opened, 'note' => null, 'admin_id' => $by->id, 'at' => now()]);

                // G7: the exact amount the buyer consented to; without a request, the rate of the day the deal opens.
                $fee = UnitFacts::buyerFee($unit, $this->settings);
                $commission = new Commission;
                $commission->forceFill([
                    'deal_id' => $deal->id,
                    'base_amount' => (int) $unit->total_price,
                    'rate_bp' => $request !== null ? $request->fee_consent_bp : $fee['bp'],
                    'amount' => $request !== null ? $request->fee_consent_amount : $fee['amount'],
                    'vat_bp' => $fee['vat_bp'],
                    'vat_amount' => $request !== null ? intdiv($request->fee_consent_amount * $fee['vat_bp'] + 5_000, 10_000) : $fee['vat'],
                    'source' => $request !== null ? 'consent' : 'rate',
                    'status' => CommissionStatus::Expected,
                ])->save();
                $request?->forceFill(['state' => RequestState::Matched])->save();

                return $deal;
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23000') {
                throw ValidationException::withMessages(['unit' => Text::get('deals.errors.open_deal')]);
            }

            throw $e;
        }

        $this->log->record('deal.opened', $deal, null, ['unit' => $unit->id, 'buyer' => $buyer->id, 'request' => $request?->id, 'commission' => $deal->commission?->amount]);
        $this->log->record('unit.deal_opened', $unit, null, ['deal' => $deal->reference()]);
        if ($request !== null) {
            $this->log->record('unit_request.matched', $request, ['state' => 'open'], ['state' => RequestState::Matched->value, 'deal' => $deal->reference()]);
        }
        $this->notify($deal, 'opened');

        return $deal;
    }

    /** One stage forward (G18). Seller accepted reserves the unit; completed sells it. */
    public function forward(Deal $deal, AdminUser $by, ?string $note): void
    {
        $from = $deal->stage;
        $to = $from->next();
        if ($to === null) {
            throw ValidationException::withMessages(['stage' => Text::get('deals.errors.final')]);
        }
        $this->move($deal, $by, $to, $note);
        if ($to === DealStage::SigningPayment) {
            $this->notify($deal, 'approval');
        }
        if ($to === DealStage::Completed) {
            $this->closeOtherRequests($deal);
            $this->notify($deal, 'completed');
        }
    }

    /** One stage back, with a note (G18). Before the seller's acceptance the unit is published again. */
    public function back(Deal $deal, AdminUser $by, string $note): void
    {
        $to = $deal->stage->previous();
        if ($to === null) {
            throw ValidationException::withMessages(['stage' => Text::get('deals.errors.no_back')]);
        }
        $this->move($deal, $by, $to, $note);
    }

    /** Cancel at any open stage, with a reason (G18): the unit returns to the market, the commission is waived. */
    public function cancel(Deal $deal, AdminUser $by, string $reason): void
    {
        if (! $deal->stage->isOpen()) {
            throw ValidationException::withMessages(['stage' => Text::get('deals.errors.final')]);
        }
        $this->move($deal, $by, DealStage::Cancelled, $reason);
        $this->notify($deal, 'cancelled');
    }

    private function move(Deal $deal, AdminUser $by, DealStage $to, ?string $note): void
    {
        $from = $deal->stage;
        $unit = $deal->unit;
        if ($unit === null) {
            throw ValidationException::withMessages(['unit' => Text::get('deals.errors.unit_state')]);
        }
        $unitBefore = $unit->state;
        DB::transaction(function () use ($deal, $by, $from, $to, $note, $unit): void {
            $fill = ['stage' => $to];
            if ($to === DealStage::Completed) {
                $fill += ['completed_at' => now(), 'open_unit_id' => null];
            }
            if ($to === DealStage::Cancelled) {
                $fill += ['cancelled_at' => now(), 'cancelled_reason' => $note, 'open_unit_id' => null];
            }
            $deal->forceFill($fill)->save();
            $deal->events()->create(['from' => $from, 'to' => $to, 'note' => $note, 'admin_id' => $by->id, 'at' => now()]);

            if ($to === DealStage::Completed) {
                $unit->forceFill(['state' => UnitState::Sold, 'sold_at' => now(), 'active_contract_key' => null, 'active_unit_key' => null, 'withdraw_requested_at' => null])->save();
                $unit->pendingChanges()->where('state', 'pending')->update(['state' => 'rejected', 'note' => 'unit sold', 'reviewed_at' => now()]);
                $deal->commission?->forceFill(['status' => CommissionStatus::Due])->save();
            } elseif ($to === DealStage::Cancelled) {
                if ($unit->state === UnitState::Reserved) {
                    $unit->forceFill(['state' => UnitState::Published, 'withdraw_requested_at' => null])->save();
                }
                $deal->commission?->forceFill(['status' => CommissionStatus::Waived, 'waived_reason' => 'deal cancelled'])->save();
                $request = $deal->request;
                if ($request !== null && $request->state === RequestState::Matched) {
                    $request->forceFill(['state' => RequestState::Contacted])->save();
                }
            } elseif ($to->reservesUnit() && $unit->state === UnitState::Published) {
                $unit->forceFill(['state' => UnitState::Reserved])->save();
            } elseif (! $to->reservesUnit() && $unit->state === UnitState::Reserved) {
                $unit->forceFill(['state' => UnitState::Published])->save();
            }
        });
        if ($to === DealStage::Completed) {
            foreach ($unit->photos()->get() as $photo) {
                $this->photos->withdraw($photo);
            }
        }
        $this->log->record('deal.stage_changed', $deal, ['stage' => $from->value], ['stage' => $to->value], $note);
        if ($unit->state !== $unitBefore) {
            $this->log->record('unit.deal_state', $unit, ['state' => $unitBefore->value], ['state' => $unit->state->value, 'deal' => $deal->reference()]);
        }
    }

    /** G12: when a unit is sold, the other open requests on it close as "unit sold", each buyer told in one line. */
    private function closeOtherRequests(Deal $deal): void
    {
        $others = UnitRequest::query()->where('unit_id', $deal->unit_id)->whereIn('state', RequestState::openValues())
            ->when($deal->unit_request_id !== null, fn ($q) => $q->whereKeyNot($deal->unit_request_id))->with('buyer')->get();
        foreach ($others as $request) {
            $request->forceFill(['state' => RequestState::Closed, 'closed_reason' => 'unit sold'])->save();
            $this->log->record('unit_request.closed_sold', $request, ['state' => 'open'], ['state' => RequestState::Closed->value]);
            $buyer = $request->buyer;
            if ($buyer !== null) {
                $locale = $buyer->preferredLocale();
                $this->outbox->send($buyer->email, $buyer->name, $locale, 'buyer.request_closed_sold', [
                    'kind' => 'unit_sold', 'role' => 'buyer', 'unit_id' => $deal->unit_id,
                    'reference' => 'R-'.strtoupper(substr((string) $request->public_id, -6)),
                    'link' => Links::frontend('/opportunities', $locale),
                ]);
            }
        }
    }

    /** Both parties, each in their own language; payloads hold ids and codes only, the mail builds the words. */
    private function notify(Deal $deal, string $kind): void
    {
        foreach (['buyer' => $deal->buyer, 'seller' => $deal->seller] as $role => $person) {
            if ($person === null) {
                continue;
            }
            $locale = $person->preferredLocale();
            $this->outbox->send($person->email, $person->name, $locale, 'deal.notice', [
                'kind' => $kind, 'role' => $role, 'unit_id' => $deal->unit_id, 'reference' => $deal->reference(),
                'link' => Links::frontend($role === 'seller' ? '/account/units/'.$deal->unit?->public_id : '/account/requests', $locale),
            ]);
        }
    }
}
