<?php

namespace App\Policies;

use App\Models\ApprovalWorkflow;
use App\Models\User;

/**
 * Workflow yapılandırma — firma izolasyonu (company_id).
 * Permission middleware asıl kapı; Policy satır kapsamı.
 */
class ApprovalWorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function view(User $user, ApprovalWorkflow $workflow): bool
    {
        return $this->sameCompany($user, $workflow);
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function update(User $user, ApprovalWorkflow $workflow): bool
    {
        return $this->sameCompany($user, $workflow);
    }

    public function delete(User $user, ApprovalWorkflow $workflow): bool
    {
        return $this->sameCompany($user, $workflow);
    }

    protected function sameCompany(User $user, ApprovalWorkflow $workflow): bool
    {
        return $user->company_id !== null
            && (int) $user->company_id === (int) $workflow->company_id;
    }
}
