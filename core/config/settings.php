<?php

/*
| Definitions of every admin-editable setting: type, limits, default, and whether the default is provisional
| (not yet researched or confirmed; the dashboard lists those). Stored values live in the `settings` table and
| are read through App\Support\Settings\Settings.
|
| Types: int, bool, string, enum (with 'options'). Percentages are basis points (100 = 1%), money is whole EGP.
| 'public' => true means the value may be sent to the public site (GET /api/v1/settings).
|
| Provisional calculator values (PLAN 2.2.9 point 8): penalty 10-15% of the total price, refund over the same years
| and the 50% deep-loss line are aqarexit's hard-coded constants (docs/research/aqarexit-03-calculator.md), kept
| only as a starting point. Every value marked provisional is researched with sources before launch (PLAN 4.3).
*/

return [
    // Fees (decided 2026-11-17)
    'buyer_fee_bp' => ['type' => 'int', 'group' => 'fees', 'default' => 100, 'min' => 0, 'max' => 1000, 'public' => true],
    'vat_bp' => ['type' => 'int', 'group' => 'fees', 'default' => 0, 'min' => 0, 'max' => 3000, 'public' => true],

    // Developer transfer fee (decided 2026-11-17: the seller pays it, to the developer, not to Lokta)
    'transfer_fee_payer' => ['type' => 'enum', 'group' => 'transfer', 'default' => 'seller', 'options' => ['seller', 'buyer'], 'public' => true],
    'transfer_fee_mode' => ['type' => 'enum', 'group' => 'transfer', 'default' => 'pct', 'options' => ['pct', 'fixed'], 'public' => true, 'provisional' => true],
    'transfer_fee_bp' => ['type' => 'int', 'group' => 'transfer', 'default' => 0, 'min' => 0, 'max' => 2000, 'public' => true, 'provisional' => true],
    'transfer_fee_fixed' => ['type' => 'int', 'group' => 'transfer', 'default' => 0, 'min' => 0, 'max' => 10_000_000, 'public' => true, 'provisional' => true],

    // Seller form (PLAN 2.2)
    'receipts_required' => ['type' => 'bool', 'group' => 'seller', 'default' => false, 'public' => true],
    'installment_check_tolerance_bp' => ['type' => 'int', 'group' => 'seller', 'default' => 1000, 'min' => 0, 'max' => 5000, 'public' => true],
    'description_min' => ['type' => 'int', 'group' => 'seller', 'default' => 60, 'min' => 0, 'max' => 1000, 'public' => true],
    'description_max' => ['type' => 'int', 'group' => 'seller', 'default' => 1200, 'min' => 100, 'max' => 5000, 'public' => true],
    'photo_min_count' => ['type' => 'int', 'group' => 'seller', 'default' => 1, 'min' => 1, 'max' => 10, 'public' => true],
    'photo_max_count' => ['type' => 'int', 'group' => 'seller', 'default' => 10, 'min' => 1, 'max' => 30, 'public' => true],
    'id_retention_days' => ['type' => 'int', 'group' => 'seller', 'default' => 90, 'min' => 1, 'max' => 3650],
    'draft_id_retention_days' => ['type' => 'int', 'group' => 'seller', 'default' => 180, 'min' => 1, 'max' => 3650],

    // Buyer requests (Q15)
    'max_open_requests_per_buyer' => ['type' => 'int', 'group' => 'buyer', 'default' => 5, 'min' => 1, 'max' => 50],
    // PLAN 2.3: "Areas (multi, up to N)".
    'budget_max_areas' => ['type' => 'int', 'group' => 'buyer', 'default' => 10, 'min' => 1, 'max' => 50, 'public' => true],

    // Calculator (PLAN 4.2; provisional until researched)
    'penalty_basis' => ['type' => 'enum', 'group' => 'calculator', 'default' => 'total', 'options' => ['total', 'paid'], 'public' => true, 'provisional' => true],
    'penalty_min_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 1000, 'min' => 0, 'max' => 10000, 'public' => true, 'provisional' => true],
    'penalty_max_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 1500, 'min' => 0, 'max' => 10000, 'public' => true, 'provisional' => true],
    'late_fee_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 0, 'min' => 0, 'max' => 10000, 'public' => true, 'provisional' => true],
    'refund_rule' => ['type' => 'enum', 'group' => 'calculator', 'default' => 'mirror', 'options' => ['mirror', 'fixed'], 'public' => true, 'provisional' => true],
    'refund_years_fixed' => ['type' => 'int', 'group' => 'calculator', 'default' => 0, 'min' => 0, 'max' => 20, 'public' => true, 'provisional' => true],
    'deep_loss_ratio_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 5000, 'min' => 0, 'max' => 10000, 'public' => true, 'provisional' => true],
    'show_present_value' => ['type' => 'bool', 'group' => 'calculator', 'default' => false, 'public' => true],
    'discount_rate_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 0, 'min' => 0, 'max' => 10000, 'public' => true, 'provisional' => true],
];
