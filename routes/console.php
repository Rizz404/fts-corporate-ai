<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Schedule (PLAN_08)
|--------------------------------------------------------------------------
|
| Run `php artisan schedule:run` every minute from cron (see
| docs/OPERATIONS.md). Retention runs at 02:00 company time.
|
*/

Schedule::command('corporate:recover-turns')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('corporate:purge-data')
    ->dailyAt('02:00')
    ->timezone(config('corporate.timezone'))
    ->withoutOverlapping(120)
    ->onOneServer();
