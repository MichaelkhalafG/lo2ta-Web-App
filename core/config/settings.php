<?php

/*
| Definitions of every admin-editable setting: type, limits, default, and whether the default is provisional
| (not yet researched or confirmed; the dashboard lists those). Stored values live in the `settings` table and
| are read through App\Support\Settings\Settings.
|
| Types: int, bool, string, enum (with 'options'). Percentages are basis points (100 = 1%), money is whole EGP.
| 'public' => true means the value may be sent to the public site (GET /api/v1/settings).
|
| Calculator values (PLAN 4.3): researched 2026-11-24 (docs/research/market-values-2026-11.md), then answered and
| confirmed by the client on 2026-11-25 (relayed by Michael): cancellation 15% of the total price, one value; the
| refund in one payment; the transfer fee a fixed EGP 50,000 (a seller's own figure in step 3 replaces it); a
| late-payment penalty of 1% of each late installment, charged once, on both exits; the maintenance deposit moves
| to the buyer with the transfer (an information note). The sources, the date checked and the confirmation live in
| market_values (config/market_values.php groups them).
| Not confirmed, and not counted as provisional rates (Michael 2026-11-25): the 50% deep-loss line, a display
| threshold (J6), and the 20% discount rate (the Central Bank's overnight deposit rate from 2026-10-29), used only if
| the present-value line is switched on (it is off).
*/

return [
    // Fees (decided 2026-11-17)
    'buyer_fee_bp' => ['type' => 'int', 'group' => 'fees', 'default' => 100, 'min' => 0, 'max' => 1000, 'public' => true],
    'vat_bp' => ['type' => 'int', 'group' => 'fees', 'default' => 0, 'min' => 0, 'max' => 3000, 'public' => true],

    // Developer transfer fee (decided 2026-11-17: the seller pays it, to the developer, not to Lokta)
    'transfer_fee_payer' => ['type' => 'enum', 'group' => 'transfer', 'default' => 'seller', 'options' => ['seller', 'buyer'], 'public' => true],
    // Client 2026-11-25: a fixed amount by default (most developers charge one). transfer_fee_bp applies only if the
    // mode is switched to a percentage.
    'transfer_fee_mode' => ['type' => 'enum', 'group' => 'transfer', 'default' => 'fixed', 'options' => ['pct', 'fixed'], 'public' => true],
    'transfer_fee_bp' => ['type' => 'int', 'group' => 'transfer', 'default' => 0, 'min' => 0, 'max' => 2000, 'public' => true],
    'transfer_fee_fixed' => ['type' => 'int', 'group' => 'transfer', 'default' => 50_000, 'min' => 0, 'max' => 10_000_000, 'public' => true],

    // Seller form (PLAN 2.2)
    'receipts_required' => ['type' => 'bool', 'group' => 'seller', 'default' => false, 'public' => true],
    'installment_check_tolerance_bp' => ['type' => 'int', 'group' => 'seller', 'default' => 1000, 'min' => 0, 'max' => 5000, 'public' => true],
    'description_min' => ['type' => 'int', 'group' => 'seller', 'default' => 60, 'min' => 0, 'max' => 1000, 'public' => true],
    'description_max' => ['type' => 'int', 'group' => 'seller', 'default' => 1200, 'min' => 100, 'max' => 5000, 'public' => true],
    'photo_min_count' => ['type' => 'int', 'group' => 'seller', 'default' => 1, 'min' => 1, 'max' => 10, 'public' => true],
    'photo_max_count' => ['type' => 'int', 'group' => 'seller', 'default' => 10, 'min' => 1, 'max' => 30, 'public' => true],
    // Upload limits (PLAN 2.2.13 point 8 and 2.2.14, Michael 2026-11-18). One size cap for documents and photos.
    'upload_max_mb' => ['type' => 'int', 'group' => 'seller', 'default' => 10, 'min' => 1, 'max' => 10, 'public' => true],
    // Stored uploads per account, all units together (Michael 2026-11-18, option A): keeps one account from filling
    // the hosting disk. Counts the stored (re-encoded) bytes of documents not yet purged and of photos.
    'upload_max_account_mb' => ['type' => 'int', 'group' => 'seller', 'default' => 300, 'min' => 10, 'max' => 5000, 'public' => true],
    'photo_min_px' => ['type' => 'int', 'group' => 'seller', 'default' => 800, 'min' => 400, 'max' => 2000, 'public' => true],
    // Files per document type per unit; the national ID front and back are one file each, fixed in code.
    'doc_max_contract' => ['type' => 'int', 'group' => 'seller', 'default' => 15, 'min' => 1, 'max' => 50, 'public' => true],
    'doc_max_schedule' => ['type' => 'int', 'group' => 'seller', 'default' => 5, 'min' => 1, 'max' => 50, 'public' => true],
    'doc_max_receipt' => ['type' => 'int', 'group' => 'seller', 'default' => 30, 'min' => 1, 'max' => 100, 'public' => true],
    'doc_max_statement' => ['type' => 'int', 'group' => 'seller', 'default' => 3, 'min' => 1, 'max' => 20, 'public' => true],
    // Pixel limits, checked from the file header before any decoding (Michael 2026-11-18: the browser shrinks photos
    // to 2560 px and scans to 4000 px; the server refuses anything larger than this, so a crafted image can never
    // exhaust the 256M PHP memory of the host).
    'image_max_mp' => ['type' => 'int', 'group' => 'seller', 'default' => 25, 'min' => 1, 'max' => 25, 'public' => true],
    'image_max_side_px' => ['type' => 'int', 'group' => 'seller', 'default' => 8000, 'min' => 1000, 'max' => 8000, 'public' => true],
    // Rate limits per hour (Michael 2026-11-18, "moderate"). Over the limit: 429 with the seconds to wait.
    'rl_upload_user' => ['type' => 'int', 'group' => 'limits', 'default' => 100, 'min' => 1, 'max' => 10000],
    'rl_upload_ip' => ['type' => 'int', 'group' => 'limits', 'default' => 200, 'min' => 1, 'max' => 10000],
    'rl_submit_user' => ['type' => 'int', 'group' => 'limits', 'default' => 5, 'min' => 1, 'max' => 1000],
    'rl_submit_ip' => ['type' => 'int', 'group' => 'limits', 'default' => 10, 'min' => 1, 'max' => 1000],
    'rl_draft_user' => ['type' => 'int', 'group' => 'limits', 'default' => 120, 'min' => 1, 'max' => 10000],
    'rl_dupcheck_user' => ['type' => 'int', 'group' => 'limits', 'default' => 20, 'min' => 1, 'max' => 1000],
    // Account-wide sign-in ceiling (audit H2, Michael 2026-11-23): failures from any address within the window, then
    // a lock and an e-mail to the owner (App\Support\Accounts\LoginGuard). Staff sign-in uses the same three values
    // with its own counters (second security pass N1, Michael 2026-11-24).
    // Profile, favorites and budget writes per user and hour (audit L4).
    'rl_account_write_user' => ['type' => 'int', 'group' => 'limits', 'default' => 120, 'min' => 10, 'max' => 10000],
    'rl_login_account' => ['type' => 'int', 'group' => 'limits', 'default' => 20, 'min' => 3, 'max' => 1000],
    'login_window_minutes' => ['type' => 'int', 'group' => 'limits', 'default' => 60, 'min' => 5, 'max' => 1440],
    'login_lock_minutes' => ['type' => 'int', 'group' => 'limits', 'default' => 15, 'min' => 1, 'max' => 1440],
    'id_retention_days' => ['type' => 'int', 'group' => 'seller', 'default' => 90, 'min' => 1, 'max' => 3650],
    'draft_id_retention_days' => ['type' => 'int', 'group' => 'seller', 'default' => 180, 'min' => 1, 'max' => 3650],

    // Buyer requests (Q15)
    'max_open_requests_per_buyer' => ['type' => 'int', 'group' => 'buyer', 'default' => 5, 'min' => 1, 'max' => 50],
    // Public forms (Michael 2026-11-22, "moderate"): per hour; honeypot on each form (Q18).
    'rl_contact_ip' => ['type' => 'int', 'group' => 'limits', 'default' => 5, 'min' => 1, 'max' => 1000],
    'rl_partnership_ip' => ['type' => 'int', 'group' => 'limits', 'default' => 3, 'min' => 1, 'max' => 1000],
    'rl_request_user' => ['type' => 'int', 'group' => 'limits', 'default' => 10, 'min' => 1, 'max' => 1000],
    'rl_request_ip' => ['type' => 'int', 'group' => 'limits', 'default' => 20, 'min' => 1, 'max' => 1000],
    // Public calculator, per address and hour (Michael 2026-11-25). It stores nothing.
    'rl_calculator_ip' => ['type' => 'int', 'group' => 'limits', 'default' => 60, 'min' => 1, 'max' => 10000],
    'form_message_max' => ['type' => 'int', 'group' => 'limits', 'default' => 2000, 'min' => 200, 'max' => 10000, 'public' => true],
    // Team notices (Michael 2026-11-18): one of the agreement's two official mailboxes, e.g. info@loktaeg.com. Empty
    // until the mailbox exists: then every active admin account gets the notice (RUN D14).
    'team_email' => ['type' => 'string', 'group' => 'team', 'default' => '', 'format' => 'email_or_empty'],
    // PLAN 2.3: "Areas (multi, up to N)".
    'budget_max_areas' => ['type' => 'int', 'group' => 'buyer', 'default' => 10, 'min' => 1, 'max' => 50, 'public' => true],

    // Calculator (PLAN 4.2; provisional until researched)
    // Client 2026-11-25: 15% of the total price, one value (min = max); the refund in one payment ('lump').
    'penalty_basis' => ['type' => 'enum', 'group' => 'calculator', 'default' => 'total', 'options' => ['total', 'paid'], 'public' => true],
    'penalty_min_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 1500, 'min' => 0, 'max' => 10000, 'public' => true],
    'penalty_max_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 1500, 'min' => 0, 'max' => 10000, 'public' => true],
    // Client 2026-11-25: 1% of each late installment, charged once, whatever the installment period. Charged on
    // the overdue amount on both exits: it lowers the refund, and on a transfer it is settled with the arrears.
    'late_fee_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 100, 'min' => 0, 'max' => 10000, 'public' => true],
    'refund_rule' => ['type' => 'enum', 'group' => 'calculator', 'default' => 'lump', 'options' => ['lump', 'mirror', 'fixed'], 'public' => true],
    'refund_years_fixed' => ['type' => 'int', 'group' => 'calculator', 'default' => 0, 'min' => 0, 'max' => 20, 'public' => true],
    'deep_loss_ratio_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 5000, 'min' => 0, 'max' => 10000, 'public' => true, 'provisional' => true],
    'show_present_value' => ['type' => 'bool', 'group' => 'calculator', 'default' => false, 'public' => true],
    'discount_rate_bp' => ['type' => 'int', 'group' => 'calculator', 'default' => 2000, 'min' => 0, 'max' => 10000, 'public' => true, 'provisional' => true],
    // Information only, not in the calculation (PLAN 4.3 item 6; client 2026-11-25: it moves to the buyer).
    'maintenance_deposit_moves' => ['type' => 'bool', 'group' => 'calculator', 'default' => true, 'public' => true],
];
