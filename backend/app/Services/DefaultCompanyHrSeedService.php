<?php

namespace App\Services;

use App\Models\AccrualPolicy;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Services\Onboarding\DefaultOffboardingTemplateService;
use Illuminate\Support\Facades\DB;

/**
 * FAZ A1 — Firma için TR iş hukuku varsayılanları (izin türleri + hakediş + tatiller).
 * Idempotent: iki kez çalışınca tekrar oluşturmaz.
 */
class DefaultCompanyHrSeedService
{
    public const ANNUAL_SYSTEM_CODE = 'annual';

    public const ACCRUAL_POLICY_NAME = 'TR Yıllık İzin Hakedişi (4857)';

    /**
     * TR 4857 varsayılan izin türleri (firma kopyası).
     *
     * @return list<array<string, mixed>>
     */
    public static function leaveTypeDefinitions(): array
    {
        return [
            [
                'system_code' => 'annual',
                'code' => 'YI',
                'name' => 'Yıllık İzin',
                'description' => '4857 sayılı Kanun yıllık ücretli izin',
                'is_paid' => true,
                'deducts_from_annual' => false,
                'default_days' => 14,
                'requires_document' => false,
                'gender_restriction' => 'all',
                'max_days_at_once' => null,
                'min_days_notice' => 3,
            ],
            [
                'system_code' => 'marriage',
                'code' => 'EI',
                'name' => 'Mazeret — Evlilik',
                'description' => 'Evlilik mazeret izni (3 gün)',
                'is_paid' => true,
                'deducts_from_annual' => false,
                'default_days' => 3,
                'requires_document' => true,
                'gender_restriction' => 'all',
                'max_days_at_once' => 3,
                'min_days_notice' => 7,
            ],
            [
                'system_code' => 'bereavement',
                'code' => 'OI',
                'name' => 'Mazeret — Ölüm',
                'description' => 'Yakın ölüm mazeret izni (3 gün)',
                'is_paid' => true,
                'deducts_from_annual' => false,
                'default_days' => 3,
                'requires_document' => false,
                'gender_restriction' => 'all',
                'max_days_at_once' => 3,
                'min_days_notice' => 0,
            ],
            [
                'system_code' => 'maternity',
                'code' => 'DI',
                'name' => 'Doğum / Analık İzni',
                'description' => 'Analık izni (16 hafta / 112 gün)',
                'is_paid' => true,
                'deducts_from_annual' => false,
                'default_days' => 112,
                'requires_document' => true,
                'gender_restriction' => 'female',
                'max_days_at_once' => null,
                'min_days_notice' => 30,
            ],
            [
                'system_code' => 'paternity',
                'code' => 'BI',
                'name' => 'Babalık İzni',
                'description' => 'Babalık izni (5 gün)',
                'is_paid' => true,
                'deducts_from_annual' => false,
                'default_days' => 5,
                'requires_document' => true,
                'gender_restriction' => 'male',
                'max_days_at_once' => 5,
                'min_days_notice' => 1,
            ],
            [
                'system_code' => 'nursing',
                'code' => 'SI',
                'name' => 'Süt İzni',
                'description' => 'Süt izni (günlük saat hakkı; gün bakiyesi 0)',
                'is_paid' => true,
                'deducts_from_annual' => false,
                'default_days' => 0,
                'requires_document' => false,
                'gender_restriction' => 'female',
                'max_days_at_once' => null,
                'min_days_notice' => 0,
            ],
            [
                'system_code' => 'sick',
                'code' => 'HI',
                'name' => 'Hastalık / Rapor',
                'description' => 'Hastalık ve istirahat raporu',
                'is_paid' => true,
                'deducts_from_annual' => false,
                'default_days' => 10,
                'requires_document' => true,
                'gender_restriction' => 'all',
                'max_days_at_once' => null,
                'min_days_notice' => 0,
            ],
            [
                'system_code' => 'unpaid',
                'code' => 'UI',
                'name' => 'Ücretsiz İzin',
                'description' => 'Ücretsiz izin',
                'is_paid' => false,
                'deducts_from_annual' => false,
                'default_days' => 30,
                'requires_document' => false,
                'gender_restriction' => 'all',
                'max_days_at_once' => null,
                'min_days_notice' => 7,
            ],
            [
                'system_code' => 'adoption',
                'code' => 'EA',
                'name' => 'Evlat Edinme İzni',
                'description' => 'Evlat edinme izni',
                'is_paid' => true,
                'deducts_from_annual' => false,
                'default_days' => 56,
                'requires_document' => true,
                'gender_restriction' => 'all',
                'max_days_at_once' => null,
                'min_days_notice' => 14,
            ],
            [
                'system_code' => 'travel',
                'code' => 'YL',
                'name' => 'Yol İzni',
                'description' => 'Yol izni (4 gün)',
                'is_paid' => true,
                'deducts_from_annual' => false,
                'default_days' => 4,
                'requires_document' => false,
                'gender_restriction' => 'all',
                'max_days_at_once' => 4,
                'min_days_notice' => 1,
            ],
        ];
    }

    /**
     * TR kıdem bandları + yaş override (tenure_rules jsonb).
     *
     * @return array<string, mixed>
     */
    public static function trAnnualTenureRules(): array
    {
        return [
            'bands' => [
                ['years' => 1, 'days' => 14],  // 1–5 yıl (bekleme sonrası)
                ['years' => 5, 'days' => 20],  // 5–15 yıl
                ['years' => 15, 'days' => 26], // 15+
            ],
            'age_overrides' => [
                ['max_age' => 17, 'min_days' => 20],
                ['min_age' => 50, 'min_days' => 20],
            ],
            'waiting_period_years' => 1,
            'accrual_on' => 'hire_anniversary',
            'min_split_days' => 10,
            'holidays_excluded_from_leave' => true,
        ];
    }

    public function ensureForCompany(Company|int $company): void
    {
        $companyId = $company instanceof Company ? (int) $company->id : $company;

        DB::transaction(function () use ($companyId): void {
            $this->ensureLeaveTypes($companyId);
            $this->ensureAnnualAccrualPolicy($companyId);
        });

        // A5 — pozisyon kataloğu (ayrı transaction; leave seed'i bozmaz)
        app(PositionCatalogSeedService::class)->ensureForCompany($companyId);
        // Offboarding varsayılan şablon
        app(DefaultOffboardingTemplateService::class)->ensureForCompany($companyId);
        app(\App\Services\Salary\SalaryRecordService::class)->backfillCompany($companyId);
    }

    public function ensureForAllCompanies(): int
    {
        $count = 0;
        Company::query()->orderBy('id')->each(function (Company $company) use (&$count): void {
            $this->ensureForCompany($company);
            $count++;
        });

        Holiday::seedTurkishHolidaysForYears([2026, 2027, 2028]);

        return $count;
    }

    public function ensureLeaveTypes(int $companyId): void
    {
        foreach (self::leaveTypeDefinitions() as $def) {
            $existing = LeaveType::withoutCompanyScope()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($def) {
                    $q->where('system_code', $def['system_code'])
                        ->orWhere('code', $def['code']);
                })
                ->first();

            if ($existing) {
                // K-A: name (etiket) dokunulmaz; system_code / is_system güvenceye alınır
                $existing->forceFill([
                    'system_code' => $def['system_code'],
                    'is_system' => true,
                    'code' => $existing->code ?: $def['code'],
                    'is_paid' => $def['is_paid'],
                    'deducts_from_annual' => $def['deducts_from_annual'],
                    'is_active' => true,
                ])->save();

                continue;
            }

            LeaveType::withoutCompanyScope()->create(array_merge($def, [
                'company_id' => $companyId,
                'is_system' => true,
                'is_active' => true,
            ]));
        }
    }

    public function ensureAnnualAccrualPolicy(int $companyId): AccrualPolicy
    {
        $annual = LeaveType::withoutCompanyScope()
            ->where('company_id', $companyId)
            ->where('system_code', self::ANNUAL_SYSTEM_CODE)
            ->firstOrFail();

        $policy = AccrualPolicy::withoutCompanyScope()
            ->where('company_id', $companyId)
            ->where('leave_type_id', $annual->id)
            ->where('name', self::ACCRUAL_POLICY_NAME)
            ->first();

        $payload = [
            'company_id' => $companyId,
            'leave_type_id' => $annual->id,
            'name' => self::ACCRUAL_POLICY_NAME,
            'description' => '4857 sayılı Kanun: kıdem bandı + yaş min 20 gün; 1 yıl bekleme; yıldönümü hakediş',
            'accrual_type' => AccrualPolicy::TYPE_ANNUAL,
            'accrual_rate' => 14,
            'max_balance' => null,
            'min_balance' => 0,
            'tenure_rules' => self::trAnnualTenureRules(),
            'allow_carryover' => true,
            'max_carryover_days' => null,
            'allow_encashment' => false,
            'waiting_period_days' => 365,
            'prorate_first_year' => false,
            'is_active' => true,
        ];

        if ($policy) {
            // tenure_rules firma özelleştirmiş olabilir — yalnızca boşsa TR default yaz
            if (empty($policy->tenure_rules)) {
                $policy->forceFill(['tenure_rules' => self::trAnnualTenureRules()])->save();
            }

            return $policy;
        }

        return AccrualPolicy::withoutCompanyScope()->create($payload);
    }
}
