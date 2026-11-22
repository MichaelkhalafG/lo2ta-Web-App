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
    // Placeholder wording for the client to confirm (PLAN 6 row 23).
    'unit_received' => [
        'subject' => 'We received your unit :reference',
        'heading' => 'We received your unit details',
        'reference' => 'Reference: :reference. Please quote it whenever you contact us.',
        'review' => 'We review the details and documents within 48 hours and reply by e-mail.',
    ],
    'unit_team' => [
        'submitted' => 'New unit to review :reference',
        'resubmitted' => 'Unit resubmitted after changes :reference',
        'documents' => 'New documents on unit :reference',
        'withdrawn' => 'The seller withdrew unit :reference',
        'withdraw_request' => 'Withdrawal request on a unit with an open deal :reference',
        'next' => 'The details and documents are in the dashboard.',
    ],
    'unit_notice' => [
        'withdrawn' => [
            'subject' => 'Your unit :reference is withdrawn',
            'body' => 'Your unit :reference is off the site, as you asked. You can list it again any time from your account.',
        ],
    ],
    // Phase 5 (PLAN 2.3, 2.6). Placeholder wording for the client to confirm (PLAN 6 row 24).
    'request_received' => [
        'subject' => 'We received your request :reference',
        'heading' => 'We received your request for the unit',
        'reference' => 'Reference: :reference.',
        'fee' => 'The Lokta fee for the buyer on completion of the transfer: :fee EGP (1% of the contract price).',
        'next' => 'We will contact you by e-mail and arrange the next step with the owner. There is no booking or payment on the site.',
    ],
    'team_facts' => [
        'unit_request' => ['subject' => 'New request on a unit :reference'],
        'contact_message' => ['subject' => 'New message from the contact page'],
        'partnership_request' => ['subject' => 'New partnership request from a developer'],
        'fields' => [
            'reference' => 'Reference', 'code' => 'Unit code', 'readiness' => 'Readiness',
            'name' => 'Name', 'email' => 'E-mail', 'phone' => 'Phone', 'message' => 'Message',
            'company' => 'Company', 'contact_name' => 'Contact person', 'job_title' => 'Job title', 'projects_count' => 'Projects',
        ],
    ],
];
