<?php

namespace App\Http\Resources;

use App\Enums\PhotoKind;
use App\Enums\UnitState;
use App\Models\Feature;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Support\Calculator\ExitCalculator;
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
            // The live exit report beside step 3 and on step 6 (board 07.1): the same calculation as the PDF.
            'exit_report' => app(ExitCalculator::class)->forUnit($unit),
        ];
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
