<?php

use Illuminate\Support\Facades\Schedule;

/*
| The host runs exactly one cron line every minute: cd <core> && php artisan schedule:run (handover 2.5).
| Everything else is scheduled here.
*/

Schedule::command('outbox:send')->everyMinute()->withoutOverlapping();
