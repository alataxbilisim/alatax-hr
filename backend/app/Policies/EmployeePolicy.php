<?php

namespace App\Policies;

use App\Enums\DataScopeLevel;
use App\Models\Employee;
use App\Models\User;
use App\Services\DataScopeService;

/**
 * Employee satır yetkisi.
 * view: own/team/department/company (team kendi kaydını da içerir).
 * update/delete: yalnızca company veya department (İK) — manager team ile sadece görür.
 * company_admin yetkisi Spatie admin rolü + data_scope ile gelir.
 */
class EmployeePolicy
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->dataScope->allowsEmployee($user, $employee);
    }

    public function create(User $user): bool
    {
        return $this->dataScope->canManageHrRecords($user);
    }

    public function update(User $user, Employee $employee): bool
    {
        if (! $this->dataScope->canManageHrRecords($user)) {
            return false;
        }

        // department kapsamı: yalnızca kendi departmanındaki kayıtlar
        if ($this->dataScope->resolve($user) === DataScopeLevel::Department) {
            return $this->dataScope->allowsEmployee($user, $employee);
        }

        return true; // company
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee);
    }
}
