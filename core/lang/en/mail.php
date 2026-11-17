<?php

/*
| E-mail wording (English). Placeholder until the client's texts arrive (PLAN 6, row 18).
*/

return [
    'footer' => 'Lokta · an Egyptian marketplace for transferring installment property contracts. You received this because your e-mail was used on the site.',
    'greeting' => 'Hello :name,',
    'system_test' => [
        'subject' => 'Test message from the dashboard',
        'body' => 'This is a test message to confirm that mail works. Sent at (Cairo time): :time',
    ],
    'verify_email' => [
        'subject' => 'Confirm your e-mail address',
        'body' => 'Press the button so we know this e-mail is yours. Every update on your requests reaches you here.',
        'button' => 'Confirm my e-mail',
        'note' => 'The link works for 24 hours. If you did not register, ignore this message.',
    ],
    'reset_password' => [
        'subject' => 'Reset your password',
        'body' => 'We received a request to change your account password. Press the button to choose a new one.',
        'button' => 'Choose a new password',
        'note' => 'The link works for 60 minutes. If you did not ask for it, ignore this message and your password stays the same.',
    ],
    'new_budget' => [
        'subject' => 'A buyer registered a budget',
        'buyer' => 'Buyer',
        'cash' => 'Cash available (EGP)',
        'installment' => 'Comfortable installment (EGP)',
        'verified' => 'E-mail confirmed',
    ],
];
