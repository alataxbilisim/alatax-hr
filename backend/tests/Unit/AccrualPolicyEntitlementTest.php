<?php

namespace Tests\Unit;

use App\Models\AccrualPolicy;
use App\Services\DefaultCompanyHrSeedService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FAZ A1 — TR yıllık izin hakediş hesabı (kıdem + yaş).
 */
class AccrualPolicyEntitlementTest extends TestCase
{
    private function trPolicy(): AccrualPolicy
    {
        $policy = new AccrualPolicy;
        $policy->accrual_rate = 14;
        $policy->tenure_rules = DefaultCompanyHrSeedService::trAnnualTenureRules();

        return $policy;
    }

    #[DataProvider('entitlementCases')]
    public function test_tr_annual_entitlement(int $years, ?int $age, float $expected): void
    {
        $policy = $this->trPolicy();
        $this->assertSame($expected, $policy->calculateAnnualEntitlement($years, $age));
    }

    /**
     * @return array<string, array{0: int, 1: int|null, 2: float}>
     */
    public static function entitlementCases(): array
    {
        return [
            '3 yıl → 14' => [3, 30, 14.0],
            '8 yıl → 20' => [8, 30, 20.0],
            '16 yıl → 26' => [16, 40, 26.0],
            '52 yaş 3 yıl → 20' => [3, 52, 20.0],
            '17 yaş 1 yıl → 20' => [1, 17, 20.0],
            '0 yıl bekleme → 0' => [0, 30, 0.0],
            '15 yıl tam → 26' => [15, 45, 26.0],
            '5 yıl tam → 20' => [5, 35, 20.0],
        ];
    }

    public function test_legacy_flat_tenure_rules_still_work(): void
    {
        $policy = new AccrualPolicy;
        $policy->accrual_rate = 10;
        $policy->tenure_rules = [
            ['years' => 1, 'days' => 14],
            ['years' => 10, 'days' => 20],
        ];

        $this->assertSame(14.0, $policy->getDaysForTenure(3));
        $this->assertSame(20.0, $policy->getDaysForTenure(12));
    }
}
