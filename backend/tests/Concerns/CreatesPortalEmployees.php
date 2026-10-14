<?php

namespace Tests\Concerns;

use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

/**
 * Portal personeli: UserType::User + aktif personel kaydı (+ varsa `employee` rolü).
 */
trait CreatesPortalEmployees
{
    protected function portalEmployee(Company $company, array $userAttributes = [], array $employeeAttributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'home_company_id' => $company->id,
            'type' => UserType::User,
            'is_active' => true,
        ], $userAttributes));

        if (\Spatie\Permission\Models\Role::query()->where('name', 'employee')->where('guard_name', 'sanctum')->exists()) {
            $user->assignRole('employee');
        }

        Employee::factory()->forUser($user)->create(array_merge([
            'company_id' => $company->id,
            'status' => 'active',
        ], $employeeAttributes));

        return $user->fresh();
    }
}
