<?php

namespace App\Console\Commands;

use App\Services\Timesheet\AttendanceCalcService;
use Illuminate\Console\Command;

class MarkIncompleteAttendanceCommand extends Command
{
    protected $signature = 'timesheet:mark-incomplete {--date= : Y-m-d (varsayılan: dün)}';

    protected $description = 'Clock-out yapılmamış günlük kayıtları eksik/absent işaretler (idempotent)';

    public function handle(AttendanceCalcService $calc): int
    {
        $date = $this->option('date') ?: now()->subDay()->toDateString();
        $updated = $calc->markIncompleteForDate($date);

        $this->info("Incomplete attendance marked for {$date}: updated={$updated}");

        return self::SUCCESS;
    }
}
