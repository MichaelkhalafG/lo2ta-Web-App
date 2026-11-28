<?php

use App\Support\Activity\LogsActivity;

/*
| Every staff write is in the activity log (PLAN 2.7). Each model either logs its own creates, updates and deletes
| (LogsActivity) or is on the list below with the reason it does not; a new model must take one side, so a staff
| screen cannot change data that leaves no trace. Domain actions on the listed models (publish, verify, withdraw ...)
| write their own named log lines, each covered by the test of its screen.
*/

/** Models that do not log themselves, and why. */
const MODELS_LOGGED_ELSEWHERE = [
    'ActivityLog' => 'the log itself',
    'AdminPasswordLink' => 'its token hash must never reach the log; StaffPasswords logs who sent which link',
    'MailOutbox' => 'system queue; the action that queued a mail is logged',
    'BuyerBudget' => 'written by the buyer; logged as budget.created and budget.updated',
    'ContactMessage' => 'written by a visitor; the inbox (7.6) logs each staff change',
    'PartnershipRequest' => 'written by a visitor; the inbox (7.6) logs each staff change',
    'DuplicateAttempt' => 'itself a record of an attempt',
    'ExitReport' => 'generated; the action that generates it is logged',
    'Unit' => 'seller steps and staff actions write named log lines (unit.*)',
    'UnitDeclaration' => 'itself a record of an acceptance (text version, time, IP)',
    'UnitDocument' => 'uploads, reviews and openings write named log lines',
    'UnitPendingChange' => 'created and decided through named log lines',
    'UnitPhoto' => 'uploads and hiding write named log lines',
    'UnitRequest' => 'written by the buyer; staff changes write named log lines',
    'UnitReview' => 'itself a record of a review outcome',
    'User' => 'account actions write named log lines (user.*)',
];

it('makes every model either log itself or say why it does not', function () {
    $missing = [];
    foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
        $name = basename($file, '.php');
        $class = 'App\\Models\\'.$name;
        $logs = in_array(LogsActivity::class, class_uses_recursive($class), true);
        if (! $logs && ! array_key_exists($name, MODELS_LOGGED_ELSEWHERE)) {
            $missing[] = $name;
        }
        if ($logs) {
            expect(MODELS_LOGGED_ELSEWHERE)->not->toHaveKey($name, "{$name} logs itself; take it off the list");
        }
    }

    expect($missing)->toBe([]);
});
