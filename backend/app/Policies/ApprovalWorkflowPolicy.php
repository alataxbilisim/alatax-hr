<?php

namespace App\Policies;

use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Support\CompanyContext;

/**
 * Workflow yapılandırma — firma izolasyonu (aktif şirket bağlamı).
 * Permission middleware asıl kapı; Policy satır kapsamı.
 */
class ApprovalWorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeCompanyId($user) !== null;
    }

    public function view(User $user, ApprovalWorkflow $workflow): bool
    {
        return $this->sameCompany($user, $workflow);
    }

    public function create(User $user): bool
    {
        return $this->activeCompanyId($user) !== null;
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
        $active = $this->activeCompanyId($user);

        return $active !== null
            && (int) $active === (int) $workflow->company_id;
    }

    protected function activeCompanyId(User $user): ?int
    {
        if (CompanyContext::isBound()) {
            return CompanyContext::id();
        }

        return $user->home_company_id ? (int) $user->home_company_id : null;
    }
}
