<?php

namespace App\Policies;

use App\Enums\DataScopeLevel;
use App\Models\ApprovalRecord;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\DataScopeService;
use App\Services\WorkflowService;

/**
 * LeaveRequest satır yetkisi.
 * super_admin Gate::before; company_admin yetkisi Spatie admin + data_scope.
 */
class LeaveRequestPolicy
{
    public function __construct(
        protected DataScopeService $dataScope,
        protected WorkflowService $workflow,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->dataScope->allowsUserId($user, (int) $leaveRequest->user_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        // Kendi pending talebi veya company kapsamı
        if ((int) $leaveRequest->user_id === $user->id) {
            return true;
        }

        return $this->dataScope->resolve($user) === DataScopeLevel::Company
            && $this->dataScope->allowsUserId($user, (int) $leaveRequest->user_id);
    }

    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        // İptal: sahibi veya DataScope içinde yetkili (şube/dept/team/company)
        if ((int) $leaveRequest->user_id === $user->id) {
            return true;
        }

        return $this->dataScope->allowsUserId($user, (int) $leaveRequest->user_id);
    }

    /**
     * Onay / red:
     * - company kapsamı → tüm pending
     * - aktif workflow kaydı varsa → WorkflowService::canApprove
     * - yoksa → team (doğrudan subordinate) veya department (aynı dept)
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        $scope = $this->dataScope->resolve($user);

        if ($scope === DataScopeLevel::Company) {
            return true;
        }

        $currentRecord = $leaveRequest->approvalRecords()
            ->where('is_current', true)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->first();

        if ($currentRecord) {
            return $this->workflow->canApprove($currentRecord, $user->id);
        }

        // Legacy serbest onay KAPALI: yalnızca team / department kuralı
        if ($scope === DataScopeLevel::Team) {
            return $this->dataScope->isDirectSubordinate($user, (int) $leaveRequest->user_id);
        }

        if ($scope === DataScopeLevel::Department) {
            return $this->dataScope->allowsUserId($user, (int) $leaveRequest->user_id)
                && (int) $leaveRequest->user_id !== $user->id;
        }

        return false;
    }
}
