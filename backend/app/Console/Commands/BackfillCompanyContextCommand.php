<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Organization;
use App\Models\User;
use App\Services\CompanyContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * G1 backfill — idempotent. Migration zaten backfill eder; bu komut onarım içindir.
 */
class BackfillCompanyContextCommand extends Command
{
    protected $signature = 'group:backfill-company-context';

    protected $description = 'G1: organizations + company_user + last_company_id backfill (idempotent)';

    public function handle(CompanyContextService $service): int
    {
        $orgCreated = 0;
        $membershipCreated = 0;
        $lastUpdated = 0;

        DB::transaction(function () use ($service, &$orgCreated, &$membershipCreated, &$lastUpdated): void {
            Company::query()->orderBy('id')->each(function (Company $company) use ($service, &$orgCreated): void {
                $before = $company->organization_id;
                $service->ensureOrganizationForCompany($company->fresh() ?? $company);
                if ($before === null && ($company->fresh()?->organization_id !== null)) {
                    $orgCreated++;
                }
            });

            User::query()
                ->whereNotNull('home_company_id')
                ->orderBy('id')
                ->each(function (User $user) use ($service, &$membershipCreated, &$lastUpdated): void {
                    $had = CompanyUser::query()
                        ->where('user_id', $user->id)
                        ->where('company_id', $user->home_company_id)
                        ->exists();

                    $service->syncHomeMembership($user);

                    if (! $had) {
                        $membershipCreated++;
                    }

                    $fresh = $user->fresh();
                    if ($fresh && $fresh->last_company_id === null && $fresh->home_company_id !== null) {
                        $fresh->forceFill(['last_company_id' => $fresh->home_company_id])->saveQuietly();
                        $lastUpdated++;
                    }
                });
        });

        $this->info("organizations linked/created≈{$orgCreated}, memberships≈{$membershipCreated}, last_company≈{$lastUpdated}");

        return self::SUCCESS;
    }
}
