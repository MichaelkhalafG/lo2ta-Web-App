<?php

use Illuminate\Support\Facades\Schedule;

/*
| The host runs exactly one cron line every minute: cd <core> && php artisan schedule:run (handover 2.5).
| Everything else is scheduled here.
*/

Schedule::command('outbox:send')->everyMinute()->withoutOverlapping();

// Public sign-in tokens expire after 30 days (config/sanctum.php); expired rows are removed once a day.
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:30');

// National ID copies past their retention period (PLAN 2.2.8): 04:00 Cairo, a quiet hour.
Schedule::command('ids:purge')->dailyAt('04:00')->timezone('Africa/Cairo')->withoutOverlapping();

// Handled contact and partnership messages past their retention period (G5): 04:15 Cairo.
Schedule::command('messages:purge')->dailyAt('04:15')->timezone('Africa/Cairo')->withoutOverlapping();
