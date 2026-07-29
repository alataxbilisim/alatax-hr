<?php

namespace App\Services\Reports;

use App\Models\Company;

/**
 * Firma bazlı rapor gizlilik ayarları (company.settings.report_privacy).
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
            $enabled = (bool) $company->getSetting('report_privacy.min_cell_enabled', true);
            $threshold = (int) $company->getSetting('report_privacy.min_cell_threshold', self::DEFAULT_THRESHOLD);
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
