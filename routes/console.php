<?php

use Illuminate\Support\Facades\Schedule;

/*
| cPanel runs `php artisan schedule:run` every minute; everything below is
| driven from that single cron entry.
*/

// Weekly licence sync: Sunday 02:00 AEST. Australia/Brisbane is AEST all year
// (no DST), which avoids the skipped/repeated 02:00 hour on the NSW/VIC DST
// changeover Sundays.
Schedule::command('app:sync-venues')
    ->weeklyOn(0, '02:00')
    ->timezone('Australia/Brisbane')
    ->withoutOverlapping(180)
    ->onFailure(fn () => logger()->error('Weekly venue sync failed'));

// Shared hosting has no supervisor: drain the database queue every minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(60);

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('model:prune')->daily();
