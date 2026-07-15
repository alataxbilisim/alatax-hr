<?php

namespace App\Console\Commands;

use App\Services\Leaves\LeaveAccrualBatchService;
use Illuminate\Console\Command;

class ProcessMonthlyLeaveAccrualsCommand extends Command
{
    protected $signature = 'leaves:process-monthly-accruals
                            {--month= : Ay (1-12), varsayılan bugünün ayı}
                            {--year= : Yıl, varsayılan bugünün yılı}';

    protected $description = 'Tüm aktif firmalar için aylık izin hakedişini işler (firma bazlı hata izolasyonu)';

    public function handle(LeaveAccrualBatchService $batch): int
    {
        $month = $this->option('month') !== null ? (int) $this->option('month') : null;
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;

        $summary = $batch->processMonthlyForAllActiveCompanies($month, $year);

        $this->info(sprintf(
            'Aylık hakediş tamam: ok=%d failed=%d',
            $summary['processed'],
            $summary['failed']
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
