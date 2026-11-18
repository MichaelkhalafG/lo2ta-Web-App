<?php

namespace App\Enums;

/**
 * How a seller's overdue installments are settled (PLAN 2.2.12, decided 2026-11-17):
 * self = the seller pays them before the transfer; deduct = deducted from the amount the buyer pays the seller, and the
 * buyer pays that part to the developer. The buyer's total does not change either way.
 */
enum OverdueSettlement: string
{
    use HasLabel;

    case SellerPaysFirst = 'self';
    case Deducted = 'deduct';
}
