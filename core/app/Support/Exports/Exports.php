<?php

namespace App\Support\Exports;

use App\Models\BuyerBudget;
use App\Models\Commission;
use App\Models\Deal;
use App\Models\Unit;
use App\Models\UnitRequest;
use App\Models\User;
use App\Support\Dashboard\Text;
use App\Support\Review\FieldDisplay;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * CSV exports (G6, Michael 2026-11-27): admin only (gate export-data, checked by TableQuery), every data field of
 * deals, commissions, requests, buyers, sellers and units; document files and national ID copies are files, not
 * fields, and stay out, as do the internal duplicate keys, hashes and storage paths. Every export is logged by
 * TableQuery with who, when, which list, the filters and the row count. Times are Cairo time.
 */
final class Exports
{
    /** Unit columns that are not data for people: internal keys, ids for routing, hashes. */
    private const UNIT_SKIP = ['contract_key', 'unit_key', 'active_contract_key', 'active_unit_key', 'public_id'];

    /**
     * @return array<string, string>
     */
    public static function unitColumns(): array
    {
        $out = ['seller_name' => Text::get('exports.seller_name'), 'seller_email' => Text::get('exports.seller_email'), 'seller_phone' => Text::get('exports.seller_phone'),
            'developer_name' => Text::get('units.places.developer'), 'compound_name' => Text::get('units.places.compound'), 'area_name' => Text::get('units.places.area')];
        foreach (Schema::getColumnListing('units') as $column) {
            if (! in_array($column, self::UNIT_SKIP, true)) {
                $out[$column] = FieldDisplay::label($column);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function unitRow(Unit $unit): array
    {
        $row = [
            'seller_name' => $unit->seller?->name, 'seller_email' => $unit->seller?->email, 'seller_phone' => $unit->seller?->phone,
            'developer_name' => $unit->developer?->name_en, 'compound_name' => $unit->compound?->name_en, 'area_name' => $unit->area?->name_en,
        ];
        foreach (array_keys(self::unitColumns()) as $column) {
            if (! array_key_exists($column, $row)) {
                $row[$column] = self::cell($unit->getAttribute($column));
            }
        }

        return $row;
    }

    /**
     * @return array<string, string>
     */
    public static function dealColumns(): array
    {
        $cols = ['reference', 'unit_code', 'stage', 'buyer_name', 'buyer_email', 'buyer_phone', 'seller_name', 'seller_email', 'seller_phone', 'request', 'opened_at', 'completed_at', 'cancelled_at', 'cancelled_reason', 'commission_amount', 'commission_status'];

        return array_combine($cols, array_map(static fn (string $c): string => Text::get('exports.columns.'.$c), $cols));
    }

    /**
     * @return array<string, mixed>
     */
    public static function dealRow(Deal $d): array
    {
        return [
            'reference' => $d->reference(), 'unit_code' => $d->unit?->code, 'stage' => $d->stage->value,
            'buyer_name' => $d->buyer?->name, 'buyer_email' => $d->buyer?->email, 'buyer_phone' => $d->buyer?->phone,
            'seller_name' => $d->seller?->name, 'seller_email' => $d->seller?->email, 'seller_phone' => $d->seller?->phone,
            'request' => $d->request !== null ? 'R-'.strtoupper(substr((string) $d->request->public_id, -6)) : null,
            'opened_at' => self::cell($d->opened_at), 'completed_at' => self::cell($d->completed_at), 'cancelled_at' => self::cell($d->cancelled_at),
            'cancelled_reason' => $d->cancelled_reason, 'commission_amount' => $d->commission?->amount, 'commission_status' => $d->commission?->status->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function commissionColumns(): array
    {
        $cols = ['reference', 'unit_code', 'base_amount', 'rate_bp', 'amount', 'vat_bp', 'vat_amount', 'source', 'status', 'invoiced_on', 'collected_on', 'payment_reference', 'waived_reason', 'notes', 'created_at'];

        return array_combine($cols, array_map(static fn (string $c): string => Text::get('exports.columns.'.$c), $cols));
    }

    /**
     * @return array<string, mixed>
     */
    public static function commissionRow(Commission $c): array
    {
        return [
            'reference' => $c->deal?->reference(), 'unit_code' => $c->deal?->unit?->code, 'base_amount' => $c->base_amount, 'rate_bp' => $c->rate_bp,
            'amount' => $c->amount, 'vat_bp' => $c->vat_bp, 'vat_amount' => $c->vat_amount, 'source' => $c->source, 'status' => $c->status->value,
            'invoiced_on' => $c->invoiced_on?->format('Y-m-d'), 'collected_on' => $c->collected_on?->format('Y-m-d'), 'payment_reference' => $c->reference,
            'waived_reason' => $c->waived_reason, 'notes' => $c->notes, 'created_at' => self::cell($c->created_at),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function requestColumns(): array
    {
        $cols = ['reference', 'unit_code', 'buyer_name', 'buyer_email', 'buyer_phone', 'readiness', 'question', 'fee_consent_amount', 'fee_consent_bp', 'fee_text_version', 'fee_consent_at', 'state', 'closed_reason', 'assigned_to', 'staff_note', 'created_at'];

        return array_combine($cols, array_map(static fn (string $c): string => Text::get('exports.columns.'.$c), $cols));
    }

    /**
     * @param  array<int, string>  $staff
     * @return array<string, mixed>
     */
    public static function requestRow(UnitRequest $r, array $staff): array
    {
        return [
            'reference' => 'R-'.strtoupper(substr((string) $r->public_id, -6)), 'unit_code' => $r->unit?->code,
            'buyer_name' => $r->buyer?->name, 'buyer_email' => $r->buyer?->email, 'buyer_phone' => $r->buyer?->phone,
            'readiness' => $r->readiness->value, 'question' => $r->question, 'fee_consent_amount' => $r->fee_consent_amount,
            'fee_consent_bp' => $r->fee_consent_bp, 'fee_text_version' => $r->fee_text_version, 'fee_consent_at' => self::cell($r->fee_consent_at),
            'state' => $r->state->value, 'closed_reason' => $r->closed_reason, 'assigned_to' => $r->assigned_to !== null ? ($staff[$r->assigned_to] ?? null) : null,
            'staff_note' => $r->staff_note, 'created_at' => self::cell($r->created_at),
        ];
    }

    /**
     * Sellers and buyers: the account and, for buyers, the budget.
     *
     * @return array<string, string>
     */
    public static function personColumns(): array
    {
        $cols = ['id', 'name', 'email', 'phone', 'locale', 'email_verified_at', 'suspended_at', 'suspended_reason', 'created_at', 'units', 'requests',
            'budget_cash', 'budget_installment', 'budget_frequency', 'budget_types', 'budget_areas', 'budget_compounds', 'budget_target', 'budget_bedrooms', 'budget_delivery', 'budget_updated_at'];

        return array_combine($cols, array_map(static fn (string $c): string => Text::get('exports.columns.'.$c), $cols));
    }

    /**
     * @return array<string, mixed>
     */
    public static function personRow(User $u, ?BuyerBudget $b): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'locale' => $u->preferredLocale(),
            'email_verified_at' => self::cell($u->email_verified_at), 'suspended_at' => self::cell($u->suspended_at), 'suspended_reason' => $u->suspended_reason,
            'created_at' => self::cell($u->created_at), 'units' => $u->getAttribute('units_count'), 'requests' => $u->getAttribute('requests_count'),
            'budget_cash' => $b?->cash_available, 'budget_installment' => $b?->max_installment, 'budget_frequency' => $b?->installment_frequency->value,
            'budget_types' => $b !== null ? implode('|', array_filter($b->main_types, is_string(...))) : null,
            'budget_areas' => $b?->areas->pluck('name_en')->implode('|'), 'budget_compounds' => $b?->compounds->pluck('name_en')->implode('|'),
            'budget_target' => $b?->target_price, 'budget_bedrooms' => $b?->min_bedrooms, 'budget_delivery' => $b?->delivery_pref->value,
            'budget_updated_at' => self::cell($b?->updated_at),
        ];
    }

    /**
     * The batch loader for an export: rows are streamed with a cursor, which does not eager load, so each batch of
     * 500 loads its relations at once.
     *
     * @param  list<string>  $relations
     * @return callable(list<object>): void
     */
    public static function loader(array $relations): callable
    {
        return static function (array $batch) use ($relations): void {
            $models = [];
            foreach ($batch as $item) {
                if ($item instanceof Model) {
                    $models[] = $item;
                }
            }
            if ($models !== []) {
                (new Collection($models))->load($relations);
            }
        };
    }

    /** A plain cell: codes for choices, Cairo time for moments, numbers and text as they are. */
    private static function cell(mixed $value): string|int|float|bool|null
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface && $value->format('H:i:s') === '00:00:00' => $value->format('Y-m-d'),
            $value instanceof DateTimeInterface => (new DateTimeImmutable('@'.$value->getTimestamp()))->setTimezone(new DateTimeZone('Africa/Cairo'))->format('Y-m-d H:i'),
            is_int($value), is_float($value), is_string($value), is_bool($value), $value === null => $value,
            default => null,
        };
    }
}
