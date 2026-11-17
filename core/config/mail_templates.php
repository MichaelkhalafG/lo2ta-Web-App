<?php

use App\Mail\AccountActionMail;
use App\Mail\NewBudgetMail;
use App\Mail\SystemTestMail;

/*
| Mail templates the outbox can send, by code. Each class implements App\Support\Mail\OutboxTemplate.
| Wording lives in lang/{locale}/mail.php (placeholders until the client's texts arrive, PLAN 6 row 18).
*/

return [
    'system.test' => SystemTestMail::class,
    'account.verify_email' => AccountActionMail::class,
    'account.reset_password' => AccountActionMail::class,
    'admin.new_budget' => NewBudgetMail::class,
];
