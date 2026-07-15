<?php

namespace App\Services\Onboarding;

use App\Models\Company;
use App\Models\OnboardingTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Firma için varsayılan offboarding şablonu (idempotent).
 */
class DefaultOffboardingTemplateService
{
    public const TEMPLATE_NAME = 'Standart İşten Çıkış';

    /**
     * @return list<array{title: string, description: string, type: string, is_required: bool, days_offset: int, action_key: string}>
     */
    public static function defaultTasks(): array
    {
        return [
            [
                'title' => 'Zimmet iadesi',
                'description' => 'Personelin üzerindeki tüm açık zimmetler iade edilmelidir.',
                'type' => 'custom',
                'is_required' => true,
                'days_offset' => 0,
                'action_key' => 'asset_return',
            ],
            [
                'title' => 'Evrak teslimi',
                'description' => 'Şirket evrakları ve kartlar teslim alınır.',
                'type' => 'document_fill',
                'is_required' => true,
                'days_offset' => 1,
                'action_key' => 'document_handover',
            ],
            [
                'title' => 'Erişim kapatma',
                'description' => 'Portal ve sistem erişimleri kapatılır.',
                'type' => 'system_setup',
                'is_required' => true,
                'days_offset' => 1,
                'action_key' => 'revoke_portal',
            ],
            [
                'title' => 'İbraname',
                'description' => 'İbraname belgesi hazırlanır ve imzalanır.',
                'type' => 'document_fill',
                'is_required' => true,
                'days_offset' => 2,
                'action_key' => 'clearance_form',
            ],
            [
                'title' => 'Bilgi devri',
                'description' => 'Görev ve bilgi devri tamamlanır.',
                'type' => 'meeting',
                'is_required' => true,
                'days_offset' => 2,
                'action_key' => 'knowledge_transfer',
            ],
        ];
    }

    public function ensureForCompany(Company|int $company): OnboardingTemplate
    {
        $companyId = $company instanceof Company ? (int) $company->id : $company;

        return DB::transaction(function () use ($companyId) {
            $existing = OnboardingTemplate::withoutCompanyScope()
                ->where('company_id', $companyId)
                ->where('process_type', OnboardingTemplate::TYPE_OFFBOARDING)
                ->where('name', self::TEMPLATE_NAME)
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'is_active' => true,
                    'process_type' => OnboardingTemplate::TYPE_OFFBOARDING,
                    'tasks' => self::defaultTasks(),
                    'estimated_days' => 7,
                ])->save();

                return $existing->fresh();
            }

            // Aynı tipte başka default varsa dokunma; yoksa bu default olsun
            $hasDefault = OnboardingTemplate::withoutCompanyScope()
                ->where('company_id', $companyId)
                ->where('process_type', OnboardingTemplate::TYPE_OFFBOARDING)
                ->where('is_default', true)
                ->exists();

            return OnboardingTemplate::withoutCompanyScope()->create([
                'company_id' => $companyId,
                'process_type' => OnboardingTemplate::TYPE_OFFBOARDING,
                'name' => self::TEMPLATE_NAME,
                'description' => 'İşten çıkış kontrol listesi (zimmet, evrak, erişim, ibraname, bilgi devri)',
                'tasks' => self::defaultTasks(),
                'estimated_days' => 7,
                'is_active' => true,
                'is_default' => ! $hasDefault,
            ]);
        });
    }

    public function ensureForAllCompanies(): int
    {
        $count = 0;
        Company::query()->orderBy('id')->each(function (Company $company) use (&$count): void {
            $this->ensureForCompany($company);
            $count++;
        });

        return $count;
    }
}
