<?php

namespace App\Support\Review;

use App\Enums\DeliveryStatus;
use App\Enums\Finishing;
use App\Enums\InstallmentFrequency;
use App\Enums\OverdueSettlement;
use BackedEnum;
use DateTimeInterface;

/**
 * A unit field as staff and the seller read it (PLAN 2.7, G3): its label in the asked language and its value in
 * words, money with thousands separators, choices by their label. Used by the review screen, the seller's "changed by
 * the team" list and the e-mails, so every place shows the same words.
 */
final class FieldDisplay
{
    /** Fields whose stored value is a choice code. */
    private const ENUMS = [
        'installment_frequency' => InstallmentFrequency::class,
        'overdue_settlement' => OverdueSettlement::class,
        'finishing' => Finishing::class,
        'delivery_status' => DeliveryStatus::class,
    ];

    private const MONEY = [
        'total_price', 'paid_amount', 'market_price_estimate', 'installment_amount', 'annual_extra_amount',
        'delivery_payment', 'lowest_accepted_amount', 'overdue_amount', 'maintenance_due', 'transfer_fee_fixed',
    ];

    private const BOOLEANS = ['accepts_less', 'has_overdue'];

    public static function label(string $field, ?string $locale = null): string
    {
        $text = __("seller.field_labels.{$field}", [], $locale);

        return is_string($text) && $text !== "seller.field_labels.{$field}" ? $text : $field;
    }

    /** The kind of input a staff form needs for the field. */
    public static function type(string $field): string
    {
        return match (true) {
            array_key_exists($field, self::ENUMS) => 'select',
            in_array($field, self::MONEY, true) => 'money',
            in_array($field, self::BOOLEANS, true) => 'bool',
            $field === 'next_due_date' => 'date',
            in_array($field, ['size_m2', 'garden_m2', 'roof_m2'], true) => 'decimal',
            $field === 'description' => 'text',
            $field === 'floor' => 'code',
            default => 'int',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(string $field, ?string $locale = null): array
    {
        $enum = self::ENUMS[$field] ?? null;
        if ($enum === null) {
            return [];
        }

        $out = [];
        foreach ($enum::cases() as $case) {
            $out[] = ['value' => (string) $case->value, 'label' => $case->label($locale)];
        }

        return $out;
    }

    /** The value in words; null stays null (shown as a dash). */
    public static function value(string $field, mixed $value, ?string $locale = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }
        $enum = self::ENUMS[$field] ?? null;
        if ($enum !== null && (is_string($value) || is_int($value))) {
            $case = $enum::tryFrom($value);

            return $case !== null ? $case->label($locale) : (string) $value;
        }
        if (in_array($field, self::BOOLEANS, true)) {
            $text = __((bool) $value ? 'seller.yes' : 'seller.no', [], $locale);

            return is_string($text) ? $text : ((bool) $value ? 'yes' : 'no');
        }
        if (in_array($field, self::MONEY, true) && is_numeric($value)) {
            return number_format((int) $value);
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ($field === 'transfer_fee_bp' && is_numeric($value)) {
            return rtrim(rtrim(number_format(((int) $value) / 100, 2, '.', ''), '0'), '.').'%';
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
