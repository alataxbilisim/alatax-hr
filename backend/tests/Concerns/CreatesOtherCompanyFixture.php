<?php

namespace Tests\Concerns;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Employee;

/**
 * Tenant izolasyonu testleri için "başka şirket" ve o şirkete ait personel.
 */
trait CreatesOtherCompanyFixture
{
    protected function otherCompany(array $attributes = []): Company
    {
        return Company::factory()->create(array_merge(['status' => CompanyStatus::Active], $attributes));
    }

    protected function otherCompanyEmployee(?Company $company = null, array $attributes = []): Employee
    {
        $company ??= $this->otherCompany();

        return Employee::factory()->create(array_merge(['company_id' => $company->id], $attributes));
    }
}
