<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Services\Settings\Settings;

/**
 * Firma bazlı rapor gizlilik ayarları.
 * D4a: Settings Registry birincil; company.settings.report_privacy legacy köprü.
 */
final class ReportPrivacySettings
{
    public const DEFAULT_THRESHOLD = 5;

    public const MASKED_VALUE = '(yetersiz veri)';

    /**
     * @return array{min_cell_enabled: bool, min_cell_threshold: int}
     */
    public static function forCompanyId(int $companyId): array
    {
        $company = Company::query()->find($companyId);

        return self::fromCompany($company);
    }

    /**
     * @return array{min_cell_enabled: bool, min_cell_threshold: int}
     */
    public static function fromCompany(?Company $company): array
    {
        $enabled = true;
        $threshold = self::DEFAULT_THRESHOLD;
        if ($company) {
            $scope = ['company_id' => (int) $company->id];
            $enabled = (bool) Settings::get('reports.privacy.min_cell_enabled', $scope);
            $threshold = (int) Settings::get('reports.privacy.min_cell_threshold', $scope);
        }
        if ($threshold < 1) {
            $threshold = 1;
        }

        return [
            'min_cell_enabled' => $enabled,
            'min_cell_threshold' => $threshold,
        ];
    }
}
