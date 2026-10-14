<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Panel yolu için şirket bağlamı: Sanctum oturumu + `X-Company-Id` başlığı.
 */
trait SetsCompanyContext
{
    /**
     * @param  list<string>  $abilities
     */
    protected function actingAsInCompany(User $user, ?Company $company = null, array $abilities = ['*']): static
    {
        Sanctum::actingAs($user, $abilities);

        $companyId = $company?->id ?? $user->home_company_id;
        if ($companyId !== null) {
            $this->withHeader('X-Company-Id', (string) $companyId);
        }

        return $this;
    }
}
