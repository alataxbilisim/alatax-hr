<?php

namespace App\Policies;

use App\Enums\DataScopeLevel;
use App\Models\ApprovalRecord;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\DataScopeService;
use App\Services\WorkflowService;

/**
 * ExpenseClaim satır yetkisi.
 * update/delete: yalnızca sahibi + draft (onaylanmışa dokunulmaz).
 * approve: company | workflow canApprove | team subordinate — legacy serbest onay yok.
 */
class ExpenseClaimPolicy
{
    public function __construct(
        protected DataScopeService $dataScope,
        protected WorkflowService $workflow,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExpenseClaim $claim): bool
    {
        return $this->dataScope->allowsUserId($user, (int) $claim->user_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ExpenseClaim $claim): bool
    {
        return (int) $claim->user_id === $user->id
            && $claim->status === ExpenseClaim::STATUS_DRAFT;
    }

    public function delete(User $user, ExpenseClaim $claim): bool
    {
        if ((int) $claim->user_id !== $user->id) {
            return false;
        }

        return in_array($claim->status, [
            ExpenseClaim::STATUS_DRAFT,
            ExpenseClaim::STATUS_SUBMITTED,
        ], true);
    }

    public function approve(User $user, ExpenseClaim $claim): bool
    {
        $scope = $this->dataScope->resolve($user);

        if ($scope === DataScopeLevel::Company) {
            return true;
        }

        $actorRecord = $this->workflow->findPendingRecordForActor($claim, $user->id);

        if ($actorRecord) {
            return true;
        }

        $hasPendingWorkflow = $claim->approvalRecords()
            ->where('is_current', true)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->exists();

        if ($hasPendingWorkflow) {
            return false;
        }

        if ($scope === DataScopeLevel::Team) {
            return $this->dataScope->isDirectSubordinate($user, (int) $claim->user_id);
        }

        if ($scope === DataScopeLevel::Department) {
            return $this->dataScope->allowsUserId($user, (int) $claim->user_id)
                && (int) $claim->user_id !== $user->id;
        }

        return false;
    }
}
