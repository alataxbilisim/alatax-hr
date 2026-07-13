<?php

namespace App\Console\Commands;

use App\Services\DefaultLeaveApprovalWorkflowService;
use Illuminate\Console\Command;

class SeedDefaultLeaveApprovalWorkflows extends Command
{
    protected $signature = 'approvals:seed-default-leave-workflows';

    protected $description = 'Tüm firmalara varsayılan izin onay akışını idempotent olarak ekler';

    public function handle(DefaultLeaveApprovalWorkflowService $service): int
    {
        $count = $service->ensureForAllCompanies();
        $this->info("Varsayılan izin onay akışı güvenceye alındı ({$count} firma).");

        return self::SUCCESS;
    }
}
