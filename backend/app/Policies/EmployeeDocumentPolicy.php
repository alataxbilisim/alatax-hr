<?php

namespace App\Policies;

use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\DataScopeService;

/**
 * Personel evrakları (employee_documents).
 * Görünürlük: is_visible_to_employee — personel kendi kaydında false ise göremez.
 * İK/manager DataScope ile görür (bayraktan bağımsız).
 */
class EmployeeDocumentPolicy
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EmployeeDocument $document): bool
    {
        $employee = $document->relationLoaded('employee')
            ? $document->employee
            : $document->employee()->first();

        if (! $employee) {
            return false;
        }

        // Personel kendi evrakı: yalnızca görünür olanlar
        if ((int) $employee->user_id === $user->id) {
            return (bool) $document->is_visible_to_employee;
        }

        // İK / manager: DataScope (team/dept/company)
        return $this->dataScope->allowsEmployee($user, $employee);
    }

    public function create(User $user): bool
    {
        return $this->dataScope->canManageHrRecords($user);
    }

    /**
     * Belirli personel için yükleme — parent Employee kapsamı.
     */
    public function createForEmployee(User $user, \App\Models\Employee $employee): bool
    {
        return $this->dataScope->canManageHrRecords($user)
            && $this->dataScope->allowsEmployee($user, $employee);
    }

    public function update(User $user, EmployeeDocument $document): bool
    {
        $employee = $document->relationLoaded('employee')
            ? $document->employee
            : $document->employee()->first();

        if (! $employee) {
            return false;
        }

        return $this->dataScope->canManageHrRecords($user)
            && $this->dataScope->allowsEmployee($user, $employee);
    }

    public function delete(User $user, EmployeeDocument $document): bool
    {
        return $this->update($user, $document);
    }
}
