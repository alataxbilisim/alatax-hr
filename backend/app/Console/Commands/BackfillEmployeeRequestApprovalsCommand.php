<?php

namespace App\Console\Commands;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\EmployeeRequest;
use App\Services\WorkflowService;
use Illuminate\Console\Command;

/**
 * W1 geçiş: instance'sız bekleyen personel taleplerine onay instance üretir.
 * Workflow yoksa kayıt pending kalır (otomatik onay yok — leave/expense ile tutarlı).
 */
class BackfillEmployeeRequestApprovalsCommand extends Command
{
    protected $signature = 'approvals:backfill-employee-requests
                            {--company= : Yalnız bu company_id}
                            {--dry-run : Yazma yapma}';

    protected $description = 'W1: pending employee_request kayıtları için approval_instance backfill';

    public function handle(WorkflowService $workflows): int
    {
        $query = EmployeeRequest::query()
            ->whereIn('status', [
                EmployeeRequest::STATUS_PENDING,
                EmployeeRequest::STATUS_IN_REVIEW,
            ]);

        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }

        $dry = (bool) $this->option('dry-run');
        $started = 0;
        $skippedHasInstance = 0;
        $skippedNoWorkflow = 0;

        $query->orderBy('id')->chunkById(100, function ($rows) use ($workflows, $dry, &$started, &$skippedHasInstance, &$skippedNoWorkflow): void {
            foreach ($rows as $request) {
                if ($workflows->hasOpenInstance($request)) {
                    $skippedHasInstance++;

                    continue;
                }

                $hasWorkflow = ApprovalWorkflow::query()
                    ->where('company_id', $request->company_id)
                    ->where('entity_type', ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST)
                    ->where('is_active', true)
                    ->exists();

                if (! $hasWorkflow) {
                    $skippedNoWorkflow++;

                    continue;
                }

                if ($dry) {
                    $this->line("dry-run: would start #{$request->id}");
                    $started++;

                    continue;
                }

                $record = $workflows->startWorkflow($request, [
                    'requester_id' => $request->created_by,
                    'priority' => $request->priority,
                    'request_type_id' => (int) $request->request_type_id,
                ]);

                if ($record) {
                    $started++;
                } else {
                    $skippedNoWorkflow++;
                }
            }
        });

        $this->info("started={$started} skipped_has_instance={$skippedHasInstance} skipped_no_workflow={$skippedNoWorkflow}");
        $this->info('open_instances='.ApprovalInstance::query()
            ->where('approvable_type', EmployeeRequest::class)
            ->whereIn('status', [
                ApprovalInstance::STATUS_PENDING,
                ApprovalInstance::STATUS_IN_PROGRESS,
            ])
            ->count());

        return self::SUCCESS;
    }
}
