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
    'login_locked' => [
        'subject' => 'We paused sign-in to your account',
        'body' => 'There were many sign-in attempts with a wrong password on your account, so we paused sign-in for a while to protect it. If it was you, wait a little and try again, or choose a new password to sign in straight away.',
        'button' => 'Choose a new password',
        'note' => 'If it was not you, change your password now. The pause stops every sign-in attempt until it ends.',
    ],
    // Staff (second security pass N1): the lock runs out; an admin can send a set-password link (G13).
    'staff_login_locked' => [
        'subject' => 'Sign-in to your dashboard account is paused',
        'body' => 'There were many sign-in attempts with a wrong password on your Lokta dashboard account, so we paused sign-in for a while to protect it. If it was you, wait until the pause ends and try again.',
        'button' => 'Sign-in page',
        'note' => 'If it was not you, tell an admin straight away so they can send you a link to choose a new password. The pause stops every sign-in attempt until it ends.',
    ],
    'staff_invite' => [
        'subject' => 'Your Lokta dashboard account',
        'body' => 'An account was made for you on the Lokta dashboard. Press the button to choose your password, then sign in with your e-mail.',
        'button' => 'Choose my password',
        'note' => 'The link works once, for 60 minutes. If it runs out, ask an admin to send a new one.',
    ],
    'staff_reset' => [
        'subject' => 'Choose a new dashboard password',
        'body' => 'An admin sent you a link to choose a new password for your Lokta dashboard account. Your current password keeps working until you use it.',
        'button' => 'Choose a new password',
        'note' => 'The link works once, for 60 minutes. If you did not expect it, tell an admin.',
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
        // Part 7.2 (G20): our wording, for the client to review.
        'changes_requested' => [
            'subject' => 'Your unit :reference needs a few changes',
            'body' => 'We reviewed your unit :reference and need a few changes before we publish it. Open your account, change what is listed below and send it again.',
        ],
        'rejected' => [
            'subject' => 'We cannot publish your unit :reference',
            'body' => 'We reviewed your unit :reference and cannot publish it on Lokta. The reason is below. If you have a question, reply to this e-mail.',
        ],
        'published' => [
            'subject' => 'Your unit :reference is published',
            'body' => 'Your unit is on Lokta with the code :reference. Interested buyers contact us, and we come back to you.',
        ],
        'published_verified' => [
            'subject' => 'Your unit :reference is published as verified',
            'body' => 'Your unit is on Lokta with the code :reference and the "verified" mark, after we reviewed its documents. Interested buyers contact us, and we come back to you.',
        ],
        'verified' => [
            'subject' => 'Your unit :reference is now verified',
            'body' => 'We added the "verified" mark to your unit :reference on the site.',
        ],
        'withdraw_declined' => [
            'subject' => 'Your request to withdraw :reference',
            'body' => 'We received your request to withdraw :reference, but a deal is open on it and we cannot withdraw it now. The reason is below, and we will contact you.',
        ],
        'change_rejected' => [
            'subject' => 'The changes to your unit :reference were not approved',
            'body' => 'We reviewed the figures you changed on your unit :reference and cannot approve them. The unit stays listed with its earlier figures. The reason is below.',
        ],
        'reason' => 'Reason',
        'fields' => 'What to change',
        'changes' => 'The figures you sent',
        'documents' => 'Documents we need a clearer copy of',
        'report' => 'The exit report after our review is attached; you can download it any time from your account.',
        'open_unit' => 'See your unit on the site',
        'open_account' => 'Open your account',
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
