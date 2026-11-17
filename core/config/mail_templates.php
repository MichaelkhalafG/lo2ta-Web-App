<?php

use App\Mail\SystemTestMail;

/*
| Mail templates the outbox can send, by code. Each class implements App\Support\Mail\OutboxTemplate.
| The seller, buyer and deal e-mails are added in their phases (PLAN 2.2.7, 2.3, 2.4).
*/

return [
    'system.test' => SystemTestMail::class,
];
