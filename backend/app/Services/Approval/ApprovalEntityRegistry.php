<?php

namespace App\Services\Approval;

use App\Models\ApprovalWorkflow;
use App\Models\DataSubjectRequest;
use App\Models\EmployeeRequest;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\SalaryReviewPeriod;
use InvalidArgumentException;

/**
 * Onaya tabi entity kataloğu — D1a / D2b / D4a registry deseni.
 *
 * DoD: Yeni onaya tabi entity buraya kaydedilmeden ve yapısal testten geçmeden bitmiş sayılmaz.
 */
class ApprovalEntityRegistry
{
    /** @var array<string, ApprovalEntityDefinition>|null */
    private ?array $definitions = null;

    /**
     * @return array<string, ApprovalEntityDefinition>
     */
    public function all(): array
    {
        if ($this->definitions === null) {
            $list = $this->buildDefinitions();
            $this->definitions = [];
            foreach ($list as $def) {
                $this->definitions[$def->entityType] = $def;
            }
        }

        return $this->definitions;
    }

    public function get(string $entityType): ApprovalEntityDefinition
    {
        $all = $this->all();
        if (! isset($all[$entityType])) {
            throw new InvalidArgumentException('Bilinmeyen approval entity: '.$entityType);
        }

        return $all[$entityType];
    }

    public function has(string $entityType): bool
    {
        return isset($this->all()[$entityType]);
    }

    public function findByModelClass(string $modelClass): ?ApprovalEntityDefinition
    {
        foreach ($this->all() as $def) {
            if ($def->modelClass === $modelClass) {
                return $def;
            }
        }

        return null;
    }

    /**
     * Stüdyo entity-types + FormRequest Rule::in kaynağı.
     *
     * @return array<string, string>
     */
    public function entityTypeLabels(): array
    {
        $out = [];
        foreach ($this->all() as $def) {
            $out[$def->entityType] = $def->labelTr;
        }

        return $out;
    }

    /**
     * B3 condition-meta — tüm kayıtlı entity alanlarının birleşimi.
     *
     * @return list<string>
     */
    public function conditionFields(): array
    {
        $fields = [];
        foreach ($this->all() as $def) {
            foreach ($def->conditionFields as $field) {
                $fields[$field] = true;
            }
        }

        return array_keys($fields);
    }

    /**
     * @return list<ApprovalEntityDefinition>
     */
    private function buildDefinitions(): array
    {
        return [
            new ApprovalEntityDefinition(
                entityType: ApprovalWorkflow::ENTITY_LEAVE_REQUEST,
                modelClass: LeaveRequest::class,
                trigger: 'create',
                triggerPoint: 'PortalLeaveController@store + LeaveRequestController@store → WorkflowService::startWorkflow',
                onApproved: 'LeaveRequest::onWorkflowCompleted → status=approved + bakiye approvePending',
                onRejected: 'LeaveRequest::onWorkflowRejected → status=rejected + bakiye rejectPending',
                conditionFields: ['total_days', 'leave_type_id', 'user_id', 'requester_id', 'department_id'],
                cancelBehavior: 'LeaveRequestCancelService açık instance/record kapatır (cancelled/skipped)',
                labelTr: 'İzin Talebi',
            ),
            new ApprovalEntityDefinition(
                entityType: ApprovalWorkflow::ENTITY_EXPENSE_REQUEST,
                modelClass: ExpenseClaim::class,
                trigger: 'submit',
                triggerPoint: 'PortalExpenseController@submit → WorkflowService::startWorkflow',
                onApproved: 'ExpenseClaim::onWorkflowCompleted → status=approved',
                onRejected: 'ExpenseClaim::onWorkflowRejected → status=rejected',
                conditionFields: ['amount', 'requester_id', 'user_id', 'department_id'],
                cancelBehavior: 'Portal soft-delete; açık instance varsa WorkflowService::cancelOpenInstances (bağlantı katmanı)',
                labelTr: 'Masraf Talebi',
            ),
            new ApprovalEntityDefinition(
                entityType: ApprovalWorkflow::ENTITY_EMPLOYEE_REQUEST,
                modelClass: EmployeeRequest::class,
                trigger: 'create',
                triggerPoint: 'PortalRequestController@store → WorkflowService::startWorkflow (requires_approval)',
                onApproved: 'EmployeeRequest::onWorkflowCompleted → status=approved + history',
                onRejected: 'EmployeeRequest::onWorkflowRejected → status=rejected + history',
                conditionFields: ['priority', 'request_type_id', 'requester_id', 'department_id'],
                cancelBehavior: 'EmployeeRequest::cancel → WorkflowService::cancelOpenInstances',
                labelTr: 'Personel Talebi',
            ),
            new ApprovalEntityDefinition(
                entityType: ApprovalWorkflow::ENTITY_SALARY_REVIEW,
                modelClass: SalaryReviewPeriod::class,
                trigger: 'submit',
                triggerPoint: 'SalaryReviewService::submit → WorkflowService::startWorkflow',
                onApproved: 'SalaryReviewPeriod::onWorkflowCompleted → SalaryReviewApplyService',
                onRejected: 'SalaryReviewPeriod::onWorkflowRejected → status=rejected',
                conditionFields: ['requester_id', 'department_id'],
                cancelBehavior: 'Dönem iptali yok (W1); açık instance motor üzerinden sonuçlanır',
                labelTr: 'Zam Dönemi',
            ),
            new ApprovalEntityDefinition(
                entityType: ApprovalWorkflow::ENTITY_DATA_SUBJECT_REQUEST,
                modelClass: DataSubjectRequest::class,
                trigger: 'create',
                triggerPoint: 'DataSubjectRequestService::create/verifyIdentity → startReviewWorkflow',
                onApproved: 'DataSubjectRequest::onWorkflowCompleted → status=in_review (yanıt hattı ayrı)',
                onRejected: 'DataSubjectRequest::onWorkflowRejected → rejection_reason',
                conditionFields: ['requester_id', 'department_id'],
                cancelBehavior: 'KVKK talebi iptali ayrı; açık instance cancelOpenInstances ile kapanır',
                labelTr: 'KVKK Veri Sahibi Talebi',
            ),
        ];
    }
}
