<?php

namespace App\Services;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * İzin için varsayılan tek-adımlı dynamic_manager akışı (idempotent).
 */
class DefaultLeaveApprovalWorkflowService
{
    public const WORKFLOW_NAME = 'Varsayılan İzin Onayı';

    public function ensureForCompany(Company|int $company): ApprovalWorkflow
    {
        $companyId = $company instanceof Company ? (int) $company->id : $company;

        return DB::transaction(function () use ($companyId) {
            $workflow = ApprovalWorkflow::query()
                ->where('company_id', $companyId)
                ->where('entity_type', ApprovalWorkflow::ENTITY_LEAVE_REQUEST)
                ->where('is_default', true)
                ->first();

            if ($workflow) {
                $this->ensureDefaultStep($workflow);

                return $workflow;
            }

            $workflow = ApprovalWorkflow::create([
                'company_id' => $companyId,
                'name' => self::WORKFLOW_NAME,
                'entity_type' => ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
                'description' => 'Sistem varsayılanı: talep edenin yöneticisi (Employee.manager_id)',
                'is_active' => true,
                'is_default' => true,
                'conditions' => null,
            ]);

            $this->ensureDefaultStep($workflow);

            return $workflow;
        });
    }

    public function ensureForAllCompanies(): int
    {
        $count = 0;

        Company::query()->orderBy('id')->each(function (Company $company) use (&$count): void {
            $this->ensureForCompany($company);
            $count++;
        });

        return $count;
    }

    protected function ensureDefaultStep(ApprovalWorkflow $workflow): void
    {
        $exists = $workflow->steps()
            ->where('approver_type', ApprovalStep::APPROVER_DYNAMIC_MANAGER)
            ->where('step_order', 1)
            ->exists();

        if ($exists) {
            return;
        }

        // Eski direct_manager varsayılanı varsa dokunma
        if ($workflow->steps()->where('step_order', 1)->exists()) {
            return;
        }

        ApprovalStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'Direkt Yönetici',
            'approver_type' => ApprovalStep::APPROVER_DYNAMIC_MANAGER,
            'is_required' => true,
            'can_skip' => false,
            'completion_policy' => 'all',
        ]);
    }
}
