<?php

use App\Mail\AccountActionMail;
use App\Mail\BuyerRequestMail;
use App\Mail\NewBudgetMail;
use App\Mail\SellerUnitNoticeMail;
use App\Mail\SystemTestMail;
use App\Mail\TeamFactsMail;
use App\Mail\UnitReceivedMail;
use App\Mail\UnitTeamNoticeMail;

/*
| Mail templates the outbox can send, by code. Each class implements App\Support\Mail\OutboxTemplate.
| Wording lives in lang/{locale}/mail.php (placeholders until the client's texts arrive, PLAN 6 row 18).
*/

return [
    'system.test' => SystemTestMail::class,
    'account.verify_email' => AccountActionMail::class,
    'account.reset_password' => AccountActionMail::class,
    'account.login_locked' => AccountActionMail::class,
    'admin.login_locked' => AccountActionMail::class,
    'admin.staff_invite' => AccountActionMail::class,
    'admin.staff_reset' => AccountActionMail::class,
    'admin.new_budget' => NewBudgetMail::class,
    'seller.unit_received' => UnitReceivedMail::class,
    'admin.unit_submitted' => UnitTeamNoticeMail::class,
    'seller.unit_withdrawn' => SellerUnitNoticeMail::class,
    'buyer.request_received' => BuyerRequestMail::class,
    'admin.unit_request' => TeamFactsMail::class,
    'admin.contact_message' => TeamFactsMail::class,
    'admin.partnership_request' => TeamFactsMail::class,
];
