<?php

namespace App\Console\Commands;

use App\Services\Leaves\LeaveAccrualBatchService;
use Illuminate\Console\Command;

class ProcessLeaveCarryoverCommand extends Command
{
    protected $signature = 'leaves:process-year-carryover
                            {--year= : Kapatılacak yıl (fromYear); varsayılan geçen yıl}';

    protected $description = 'Tüm aktif firmalar için yıl sonu izin devrini işler (firma bazlı hata izolasyonu)';

    public function handle(LeaveAccrualBatchService $batch): int
    {
        $fromYear = $this->option('year') !== null ? (int) $this->option('year') : null;

        $summary = $batch->processCarryoverForAllActiveCompanies($fromYear);

        $this->info(sprintf(
            'Yıl devri tamam: ok=%d failed=%d',
            $summary['processed'],
            $summary['failed']
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
