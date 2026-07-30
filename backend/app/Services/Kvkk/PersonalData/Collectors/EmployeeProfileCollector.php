<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\Employee;
use App\Models\User;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class EmployeeProfileCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'employee_profile';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.employeeProfile';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId) {
            return [];
        }

        $user = User::query()->where('company_id', $companyId)->where('id', $userId)->first();
        $employee = Employee::query()->where('company_id', $companyId)->where('user_id', $userId)->first();

        $records = [];
        if ($user) {
            $records[] = [
                'source' => 'users',
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ];
        }
        if ($employee) {
            $records[] = [
                'source' => 'employees',
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'national_id' => $employee->national_id,
                'birth_date' => $employee->birth_date,
                'address' => $employee->address,
                'phone' => $employee->phone,
                'blood_type' => $employee->blood_type,
                'iban' => $employee->iban,
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
                'hire_date' => $employee->hire_date,
                'status' => $employee->status,
                'custom_fields' => $employee->custom_fields,
            ];
        }

        return $records === [] ? [] : [[
            'category' => 'identity',
            'label' => 'Profil / özlük',
            'records' => $records,
            'files' => $user?->avatar ? [['path' => (string) $user->avatar, 'name' => 'avatar']] : [],
        ]];
    }

    public function destroy(int $subjectId, int $companyId, string $strategy): void
    {
        // D2c
    }
}
