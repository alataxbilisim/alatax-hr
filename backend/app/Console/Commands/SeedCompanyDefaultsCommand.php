<?php

namespace App\Console\Commands;

use App\Services\DefaultCompanyHrSeedService;
use App\Services\DefaultLeaveApprovalWorkflowService;
use Illuminate\Console\Command;

class SeedCompanyDefaultsCommand extends Command
{
    protected $signature = 'alatax:seed-defaults';

    protected $description = 'Mevcut firmalara TR varsayılan izin türleri, hakediş politikası ve resmi tatilleri idempotent seed eder';

    public function handle(
        DefaultCompanyHrSeedService $hrSeed,
        DefaultLeaveApprovalWorkflowService $workflowSeed,
    ): int {
        $companies = $hrSeed->ensureForAllCompanies();
        $workflows = $workflowSeed->ensureForAllCompanies();

        $this->info("TR HR defaults güvenceye alındı ({$companies} firma; {$workflows} workflow; tatiller 2026–2028).");

        return self::SUCCESS;
    }
}
