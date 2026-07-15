<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Zamanlanmış işler (scheduler container: php artisan schedule:work)
|--------------------------------------------------------------------------
*/

Schedule::command('leaves:process-monthly-accruals')
    ->monthlyOn(1, '01:15')
    ->withoutOverlapping(180)
    ->onOneServer()
    ->name('leaves-monthly-accruals');

Schedule::command('leaves:process-year-carryover')
    ->yearlyOn(1, 1, '02:00')
    ->withoutOverlapping(180)
    ->onOneServer()
    ->name('leaves-year-carryover');

Schedule::command('documents:process-expiry-alerts')
    ->dailyAt('06:30')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->name('documents-expiry-alerts');

Schedule::command('approvals:process-escalations')
    ->dailyAt('07:00')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->name('approvals-escalations');

Schedule::command('timesheet:mark-incomplete')
    ->dailyAt('01:30')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->name('timesheet-mark-incomplete');
