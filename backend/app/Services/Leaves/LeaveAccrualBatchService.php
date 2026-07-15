<?php

namespace App\Services\Leaves;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Services\LeaveCalculationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Zamanlanmış izin hakediş / yıl devri — tüm aktif firmalar, hata izolasyonu.
 * Controller tek-firma yolu LeaveCalculationService kullanmaya devam eder.
 */
class LeaveAccrualBatchService
{
    public function __construct(
        protected LeaveCalculationService $leaveCalculation,
    ) {}

    /**
     * @return array{processed: int, failed: int, companies: list<array<string, mixed>>}
     */
    public function processMonthlyForAllActiveCompanies(?int $month = null, ?int $year = null): array
    {
        $month ??= (int) now()->month;
        $year ??= (int) now()->year;

        return $this->runForAllActiveCompanies(
            'monthly_accrual',
            function (int $companyId) use ($month, $year): array {
                $details = $this->leaveCalculation->processMonthlyAccruals($companyId, $month, $year);

                return [
                    'processed_count' => count($details),
                    'month' => $month,
                    'year' => $year,
                ];
            }
        );
    }

    /**
     * @return array{processed: int, failed: int, companies: list<array<string, mixed>>}
     */
    public function processCarryoverForAllActiveCompanies(?int $fromYear = null): array
    {
        // Yılbaşı koşusu: bir önceki yılı kapat
        $fromYear ??= (int) now()->subYear()->year;

        return $this->runForAllActiveCompanies(
            'year_end_carryover',
            function (int $companyId) use ($fromYear): array {
                $details = $this->leaveCalculation->processYearEndCarryover($companyId, $fromYear);

                return [
                    'processed_count' => count($details),
                    'from_year' => $fromYear,
                    'to_year' => $fromYear + 1,
                ];
            }
        );
    }

    /**
     * @param  callable(int): array<string, mixed>  $handler
     * @return array{processed: int, failed: int, companies: list<array<string, mixed>>}
     */
    protected function runForAllActiveCompanies(string $jobKey, callable $handler): array
    {
        $summary = [
            'processed' => 0,
            'failed' => 0,
            'companies' => [],
        ];

        $companies = Company::query()
            ->where('status', CompanyStatus::Active)
            ->orderBy('id')
            ->get(['id', 'name']);

        foreach ($companies as $company) {
            $companyId = (int) $company->id;

            try {
                $result = $handler($companyId);
                $summary['processed']++;
                $summary['companies'][] = [
                    'company_id' => $companyId,
                    'status' => 'ok',
                    'result' => $result,
                ];

                Log::info("scheduler.{$jobKey}.company_ok", [
                    'company_id' => $companyId,
                    'company_name' => $company->name,
                    'result' => $result,
                ]);
            } catch (Throwable $e) {
                $summary['failed']++;
                $summary['companies'][] = [
                    'company_id' => $companyId,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                Log::error("scheduler.{$jobKey}.company_failed", [
                    'company_id' => $companyId,
                    'company_name' => $company->name,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }
        }

        Log::info("scheduler.{$jobKey}.finished", [
            'processed' => $summary['processed'],
            'failed' => $summary['failed'],
            'total' => $companies->count(),
        ]);

        return $summary;
    }
}
