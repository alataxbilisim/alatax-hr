<?php

namespace App\Policies;

use App\Enums\DataScopeLevel;
use App\Models\ApprovalRecord;
use App\Models\User;
use App\Services\DataScopeService;
use App\Services\WorkflowService;

/**
 * ApprovalRecord — yalnızca atanan onaycı veya aktif vekil işleyebilir.
 * company_admin bypass Gate::before'dan gelir.
 */
class ApprovalRecordPolicy
{
    public function __construct(
        protected DataScopeService $dataScope,
        protected WorkflowService $workflow,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Atanan onaycı, aktif vekil (pending) veya company kapsamı.
     */
    public function view(User $user, ApprovalRecord $record): bool
    {
        if ($this->dataScope->resolve($user) === DataScopeLevel::Company) {
            return true;
        }

        if ((int) $record->approver_id === $user->id) {
            return true;
        }

        return $this->workflow->canApprove($record, $user->id);
    }

    public function approve(User $user, ApprovalRecord $record): bool
    {
        return $this->workflow->canApprove($record, $user->id);
    }

    public function reject(User $user, ApprovalRecord $record): bool
    {
        return $this->approve($user, $record);
    }

    public function skip(User $user, ApprovalRecord $record): bool
    {
        return $this->approve($user, $record);
    }
}
