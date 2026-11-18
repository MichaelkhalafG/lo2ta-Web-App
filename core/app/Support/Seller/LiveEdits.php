<?php

namespace App\Support\Seller;

use App\Models\Feature;
use App\Models\Unit;
use App\Models\UnitPendingChange;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Editing a published unit (PLAN 2.2.7, approved 2026-11-17, option A):
 * - free: description, façade, features: live at once and logged; a changed description puts the unit on the admin's
 *   "check after edit" list (photo changes do so in PhotosController);
 * - review: the money fields, size, garden, roof, floor, rooms, finishing, delivery: one pending change, the unit stays
 *   live with the old values until the admin approves (phase 7);
 * - locked: developer, compound, area, type, contract and unit numbers, contract date, total price: refused.
 * Every edit passes the same step checks as the wizard, on a copy of the unit, before anything is stored.
 */
final class LiveEdits
{
    public const FREE = ['description', 'facade', 'features'];

    public const REVIEW = [
        'paid_amount', 'market_price_estimate', 'installment_amount', 'installment_frequency', 'next_due_date',
        'years_left', 'annual_extra_amount', 'delivery_payment', 'accepts_less', 'lowest_accepted_amount', 'has_overdue',
        'overdue_count', 'overdue_amount', 'overdue_settlement', 'maintenance_due', 'transfer_fee_bp',
        'size_m2', 'garden_m2', 'roof_m2', 'floor', 'bedrooms', 'bathrooms', 'finishing', 'delivery_status',
        'delivery_year', 'delivery_month',
    ];

    public const LOCKED = [
        'developer', 'developer_other', 'compound', 'compound_other', 'area', 'area_other', 'main_type', 'sub_type',
        'contract_number', 'unit_number', 'contract_year', 'contract_month', 'total_price',
    ];

    private const STEP2 = ['size_m2', 'garden_m2', 'roof_m2', 'floor', 'bedrooms', 'bathrooms', 'finishing', 'delivery_status', 'delivery_year', 'delivery_month', 'facade', 'features'];

    public function __construct(private readonly StepRules $rules, private readonly ActivityLogger $log) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{live: list<string>, pending: UnitPendingChange|null}
     */
    public function apply(Unit $unit, User $user, array $input): array
    {
        $locked = array_values(array_intersect(array_keys($input), self::LOCKED));
        if ($locked !== []) {
            throw ValidationException::withMessages(array_fill_keys($locked, self::text('seller.rules.locked')));
        }

        // The edited unit, built and checked on a copy.
        $copy = clone $unit;
        $features = null;
        $step2 = array_intersect_key($input, array_flip(self::STEP2));
        $step3 = array_intersect_key($input, array_flip(array_diff(self::REVIEW, self::STEP2)));
        if ($step2 !== []) {
            $features = $this->rules->apply(2, $copy, $step2);
        }
        if ($step3 !== []) {
            $this->rules->apply(3, $copy, $step3);
        }
        if (array_key_exists('description', $input)) {
            $this->rules->apply(5, $copy, ['description' => $input['description']]);
        }
        $problems = [...$this->rules->problems(2, $copy), ...$this->rules->problems(3, $copy), ...$this->rules->problems(5, $copy)];
        if ($features !== null && $copy->sub_type !== null) {
            $main = $copy->sub_type->mainType()->value;
            $outside = Feature::query()->whereIn('id', $features)->get()->reject(fn (Feature $f): bool => in_array($main, $f->applies_to, true));
            if ($outside->isNotEmpty()) {
                $problems['features'] = self::text('seller.rules.feature_scope');
            }
        }
        if ($problems !== []) {
            throw ValidationException::withMessages($problems);
        }

        $live = [];
        $review = [];
        foreach ([...self::FREE, ...self::REVIEW] as $field) {
            if ($field === 'features') {
                continue;
            }
            $old = self::plain($unit->getAttribute($field));
            $new = self::plain($copy->getAttribute($field));
            if ($old === $new) {
                continue;
            }
            if (in_array($field, self::FREE, true)) {
                $live[$field] = [$old, $new];
            } else {
                $review[$field] = [$old, $new];
            }
        }
        $currentFeatures = $unit->features()->get()->map(fn (Feature $f): int => $f->id)->sort()->values()->all();
        $sync = $features !== null && collect($features)->sort()->values()->all() !== $currentFeatures ? $features : null;
        $featuresChanged = $sync !== null;

        $pending = DB::transaction(function () use ($unit, $copy, $live, $review, $sync): ?UnitPendingChange {
            if ($live !== []) {
                $unit->forceFill(array_map(fn (string $f) => $copy->getAttribute($f), array_combine(array_keys($live), array_keys($live))));
                if (isset($live['description'])) {
                    $unit->recheck_after_edit_at = now()->toImmutable();
                }
                $unit->save();
            }
            if ($sync !== null) {
                $unit->features()->sync($sync);
            }
            if ($review === []) {
                return null;
            }

            // One pending change per unit: a new edit merges into it, keeping each field's original value.
            $pending = $unit->pendingChanges()->where('state', 'pending')->latest('id')->first();
            if ($pending instanceof UnitPendingChange) {
                $merged = $pending->changes;
                foreach ($review as $field => [$old, $new]) {
                    $before = $merged[$field] ?? null;
                    $merged[$field] = [is_array($before) && array_key_exists(0, $before) ? $before[0] : $old, $new];
                }
                $pending->forceFill(['changes' => $merged, 'submitted_at' => now()])->save();

                return $pending;
            }

            return $unit->pendingChanges()->create(['changes' => $review, 'state' => 'pending', 'submitted_at' => now()]);
        });

        if ($live !== [] || $featuresChanged) {
            $this->log->record('unit.live_edit', $unit, array_map(fn (array $p) => $p[0], $live), [...array_map(fn (array $p) => $p[1], $live), ...($featuresChanged ? ['features' => $features] : [])]);
        }
        if ($review !== []) {
            $this->log->record('unit.change_requested', $unit, array_map(fn (array $p) => $p[0], $review), array_map(fn (array $p) => $p[1], $review));
        }

        return ['live' => array_keys($live), 'pending' => $pending];
    }

    /** A value as stored: enums as codes, dates as Y-m-d, decimals as strings. */
    private static function plain(mixed $value): int|float|string|bool|null
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_int($value), is_float($value), is_string($value), is_bool($value), $value === null => $value,
            default => null,
        };
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
