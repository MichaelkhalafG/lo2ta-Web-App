<?php

/*
| The researched calculator values (PLAN 4.3, Phase 6). Each groups the settings it sets; the numbers stay in
| `settings` (typed, validated, logged) and market_values keeps what goes with them: the date checked, the sources
| (internal only, J10), an internal note and the client's confirmation. Confirming a value clears the provisional
| flag of its settings; switching it back marks them provisional again.
|
| 'public' decides when the public site lists the value (calculator and reports page): always, or only while a
| setting is on or above zero. Re-check after 6 months; after 12 the public site marks the value "under review" (J8).
*/

return [
    'recheck_months' => 6,
    'stale_months' => 12,

    'values' => [
        'cancellation' => [
            'settings' => ['penalty_basis', 'penalty_min_bp', 'penalty_max_bp'],
            'public' => 'always',
        ],
        'refund' => [
            'settings' => ['refund_rule', 'refund_years_fixed'],
            'public' => 'always',
        ],
        'late_fee' => [
            'settings' => ['late_fee_bp'],
            'public' => ['above_zero' => 'late_fee_bp'],
        ],
        'transfer_fee' => [
            'settings' => ['transfer_fee_mode', 'transfer_fee_bp', 'transfer_fee_fixed'],
            'public' => 'always',
        ],
        'maintenance_deposit' => [
            'settings' => ['maintenance_deposit_moves'],
            'public' => 'always',
        ],
        'discount_rate' => [
            'settings' => ['discount_rate_bp'],
            'public' => ['when_on' => 'show_present_value'],
        ],
    ],
];
