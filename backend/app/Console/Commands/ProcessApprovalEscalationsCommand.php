<?php

namespace App\Console\Commands;

use App\Services\Approval\ApprovalEscalationService;
use Illuminate\Console\Command;

class ProcessApprovalEscalationsCommand extends Command
{
    protected $signature = 'approvals:process-escalations';

    protected $description = 'Pending onaylar için hatırlatma / eskalasyon bildirimi (yetki devretmez, idempotent)';

    public function handle(ApprovalEscalationService $service): int
    {
        $summary = $service->processAllActiveCompanies();

        $this->info(sprintf(
            'Onay eskalasyon: checked=%d reminded=%d escalated=%d skipped=%d failed=%d',
            $summary['checked'],
            $summary['reminded'],
            $summary['escalated'],
            $summary['skipped'],
            $summary['failed']
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
