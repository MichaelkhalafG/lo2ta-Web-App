<?php

namespace App\Http\Resources;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PhotoKind;
use App\Enums\ReviewOutcome;
use App\Enums\UnitState;
use App\Models\ActivityLog;
use App\Models\Feature;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Models\UnitReview;
use App\Support\Calculator\ExitCalculator;
use App\Support\Review\FieldDisplay;
use App\Support\Seller\StepRules;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The seller's own unit, for the wizard and My account. Only ever sent to the unit's seller (SellerUnits checks the
 * owner). It includes what the seller typed, private numbers too, because it is theirs; it never includes the
 * duplicate keys, storage paths, file hashes, reviewer flags, IP addresses or anything about other units.
 */
class SellerUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Unit $unit */
        $unit = $this->resource;
        $rules = app(StepRules::class);

        $readiness = [];
        foreach ([1, 2, 3, 4, 5] as $step) {
            $readiness['step'.$step] = $rules->problems($step, $unit);
        }

        return [
            'id' => $unit->public_id,
            'state' => ['code' => $unit->state->value, 'label' => $unit->state->label()],
            'editable' => $unit->isEditableBySeller(),
            'step_reached' => $unit->step_reached,
            'reference' => $unit->serial !== null ? sprintf('#%04d', $unit->serial) : null,
            'code' => $unit->code,
            'submitted_at' => $unit->submitted_at?->toIso8601String(),
            'updated_at' => $unit->updated_at?->toIso8601String(),
            // Named keys: a resource turns an array whose first key is numeric into a plain list.
            'steps' => [
                'step1' => [
                    'developer' => $unit->developer?->slug, 'developer_other' => $unit->developer_other,
                    'compound' => $unit->compound?->slug, 'compound_other' => $unit->compound_other,
                    'area' => $unit->area?->slug, 'area_other' => $unit->area_other,
                    'main_type' => self::code($unit->main_type), 'sub_type' => self::code($unit->sub_type),
                    'contract_number' => $unit->contract_number, 'unit_number' => $unit->unit_number,
                    'contract_year' => $unit->contract_year, 'contract_month' => $unit->contract_month,
                ],
                'step2' => [
                    'size_m2' => $unit->size_m2, 'garden_m2' => $unit->garden_m2, 'roof_m2' => $unit->roof_m2,
                    'floor' => $unit->floor, 'bedrooms' => $unit->bedrooms, 'bathrooms' => $unit->bathrooms,
                    'finishing' => self::code($unit->finishing), 'delivery_status' => self::code($unit->delivery_status),
                    'delivery_year' => $unit->delivery_year, 'delivery_month' => $unit->delivery_month,
                    'facade' => self::sides($unit->facade),
                    'features' => $unit->features->map(fn (Feature $f): string => $f->code)->values()->all(),
                ],
                'step3' => [
                    'total_price' => $unit->total_price, 'paid_amount' => $unit->paid_amount,
                    'market_price_estimate' => $unit->market_price_estimate,
                    'installment_amount' => $unit->installment_amount,
                    'installment_frequency' => self::code($unit->installment_frequency),
                    'next_due_date' => $unit->next_due_date?->toDateString(), 'years_left' => $unit->years_left,
                    'annual_extra_amount' => $unit->annual_extra_amount, 'delivery_payment' => $unit->delivery_payment,
                    'accepts_less' => $unit->accepts_less, 'lowest_accepted_amount' => $unit->lowest_accepted_amount,
                    'has_overdue' => $unit->has_overdue, 'overdue_count' => $unit->overdue_count,
                    'overdue_amount' => $unit->overdue_amount, 'overdue_settlement' => self::code($unit->overdue_settlement),
                    'maintenance_due' => $unit->maintenance_due, 'transfer_fee_bp' => $unit->transfer_fee_bp, 'transfer_fee_fixed' => $unit->transfer_fee_fixed,
                ],
                'step5' => ['description' => $unit->description],
            ],
            'documents' => $unit->documents()->orderBy('id')->get()->map(fn (UnitDocument $d): array => [
                'id' => $d->public_id,
                'type' => $d->type->value,
                'status' => $d->status->value,
                'name' => $d->original_name,
                'mime' => $d->mime,
                'bytes' => $d->bytes,
                'uploaded_at' => $d->created_at?->toIso8601String(),
                // The team's note when a clearer copy is needed (part 7.2).
                'note' => $d->status === DocumentStatus::NeedsReplacement ? $d->review_note : null,
            ])->values()->all(),
            'photos' => self::markCover($unit->photos->map(fn (UnitPhoto $p): array => [
                'id' => $p->public_id,
                'kind' => $p->kind->value,
                'is_cover' => false,
                'is_project_image' => $p->is_project_image,
                'is_hidden' => $p->is_hidden,
                'width' => $p->width,
                'height' => $p->height,
            ])->values()->all()),
            'readiness' => $readiness,
            // Non-blocking notes from the stored data (step 3: installments that do not add up).
            'warnings' => ['money' => $rules->warnings($unit)],
            // After publishing (PLAN 2.2.7): what waits for the admin, and what the seller may still do.
            'pending_change' => $unit->pendingChanges()->where('state', 'pending')->latest('id')->first()?->changes,
            'withdraw_requested' => $unit->withdraw_requested_at !== null,
            'can_edit_live' => in_array($unit->state, [UnitState::Published, UnitState::Reserved], true),
            'can_withdraw' => in_array($unit->state, [UnitState::Submitted, UnitState::ChangesRequested, UnitState::Published, UnitState::Reserved], true) && $unit->withdraw_requested_at === null,
            'has_exit_report' => $unit->exitReports()->whereNotNull('pdf_path')->exists(),
            // Part 7.2: the team's last word while it matters to the seller (changes requested, rejected), and every
            // figure the team corrected, with its reason (G3). Never a staff name.
            'review' => self::review($unit),
            // Part 7.3 (G15): the team's last decision on a figure change sent after publishing.
            'last_change' => self::lastChange($unit),
            'team_changes' => self::teamChanges($unit),
            // The live exit report beside step 3 and on step 6 (board 07.1): the same calculation as the PDF.
            'exit_report' => app(ExitCalculator::class)->forUnit($unit),
        ];
    }

    /**
     * @return array{outcome: string, reason: string|null, fields: list<array{field: string, label: string, note: string}>, documents: list<array{type: string, label: string, note: string}>, at: string}|null
     */
    private static function review(Unit $unit): ?array
    {
        if (! in_array($unit->state, [UnitState::ChangesRequested, UnitState::Rejected], true)) {
            return null;
        }
        $review = $unit->reviews()->whereIn('outcome', [ReviewOutcome::ChangesRequested->value, ReviewOutcome::Rejected->value])->latest('id')->first();
        if (! $review instanceof UnitReview) {
            return null;
        }
        $notes = $review->notes ?? [];
        $fields = [];
        foreach (is_array($notes['fields'] ?? null) ? $notes['fields'] : [] as $field => $note) {
            if (is_string($field) && is_string($note)) {
                $fields[] = ['field' => $field, 'label' => FieldDisplay::label($field), 'note' => $note];
            }
        }
        $documents = [];
        foreach (is_array($notes['documents'] ?? null) ? $notes['documents'] : [] as $row) {
            if (! is_array($row) || ! is_string($row['type'] ?? null)) {
                continue;
            }
            $type = DocumentType::tryFrom($row['type']);
            if ($type !== null) {
                $documents[] = ['type' => $type->value, 'label' => $type->label(), 'note' => is_string($row['note'] ?? null) ? $row['note'] : ''];
            }
        }

        return [
            'outcome' => $review->outcome->value,
            'reason' => $review->reason,
            'fields' => $fields,
            'documents' => $documents,
            'at' => $review->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array{state: string, note: string|null, at: string|null, fields: list<string>}|null
     */
    private static function lastChange(Unit $unit): ?array
    {
        $change = $unit->pendingChanges()->whereIn('state', ['approved', 'rejected'])->latest('reviewed_at')->latest('id')->first();
        if ($change === null) {
            return null;
        }

        return [
            'state' => $change->state,
            'note' => $change->state === 'rejected' ? $change->note : null,
            'at' => $change->reviewed_at?->timezone('Africa/Cairo')->format('Y-m-d'),
            'fields' => array_map(static fn ($f): string => FieldDisplay::label((string) $f), array_keys($change->changes)),
        ];
    }

    /**
     * @return list<array{field: string, label: string, from: string|null, to: string|null, reason: string|null, at: string}>
     */
    private static function teamChanges(Unit $unit): array
    {
        $out = [];
        $lines = ActivityLog::query()->where('subject_type', 'units')->where('subject_id', $unit->id)->where('action', 'unit.team_edit')->latest('id')->limit(50)->get();
        foreach ($lines as $line) {
            foreach ($line->after ?? [] as $field => $to) {
                $field = (string) $field;
                $out[] = [
                    'field' => $field,
                    'label' => FieldDisplay::label($field),
                    'from' => FieldDisplay::value($field, ($line->before ?? [])[$field] ?? null),
                    'to' => FieldDisplay::value($field, $to),
                    'reason' => $line->reason,
                    'at' => $line->created_at->toIso8601String(),
                ];
            }
        }

        return $out;
    }

    /**
     * The first listing photo is the cover (PLAN 2.2.2 step 5).
     *
     * @param  array<int, array<string, mixed>>  $photos
     * @return list<array<string, mixed>>
     */
    private static function markCover(array $photos): array
    {
        $photos = array_values($photos);
        foreach ($photos as $i => $photo) {
            if ($photo['kind'] === PhotoKind::Photo->value) {
                $photos[$i]['is_cover'] = true;
                break;
            }
        }

        return $photos;
    }

    private static function code(?BackedEnum $value): string|int|null
    {
        return $value?->value;
    }

    /**
     * @return list<string>
     */
    private static function sides(?int $bits): array
    {
        $out = [];
        foreach (['north' => 1, 'east' => 2, 'south' => 4, 'west' => 8] as $side => $bit) {
            if ($bits !== null && ($bits & $bit) !== 0) {
                $out[] = $side;
            }
        }

        return $out;
    }
}
