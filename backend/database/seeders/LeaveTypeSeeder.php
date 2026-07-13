<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Holiday;
use App\Services\DefaultCompanyHrSeedService;
use Illuminate\Database\Seeder;

/**
 * FAZ A1 — Mevcut firmalara TR izin türleri + hakediş; sistem tatilleri.
 */
class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(DefaultCompanyHrSeedService::class);

        Company::query()->orderBy('id')->each(function (Company $company) use ($service): void {
            $service->ensureForCompany($company);
        });

        Holiday::seedTurkishHolidaysForYears([2026, 2027, 2028]);
    }
}
