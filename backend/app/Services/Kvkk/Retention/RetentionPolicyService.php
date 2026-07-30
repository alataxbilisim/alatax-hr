<?php

namespace App\Services\Kvkk\Retention;

use App\Enums\RetentionStrategy;
use App\Enums\RetentionTriggerEvent;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\Settings\Settings;
use Illuminate\Validation\ValidationException;

class RetentionPolicyService
{
    /**
     * Firma saklama süresini uzatabilir, kısaltamaz (legal_min).
     */
    public function assertRetentionMonths(int $months, string $categoryKey = 'identity'): void
    {
        $legalMin = (int) Settings::get('legal.kvkk.retention.months.min', []);
        if ($legalMin < 1) {
            $legalMin = 6;
        }
        if ($months < $legalMin) {
            throw ValidationException::withMessages([
                'retention_months' => ["Yasal asgari {$legalMin}."],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $companyId, User $actor, array $data): RetentionPolicy
    {
        $months = (int) $data['retention_months'];
        $this->assertRetentionMonths($months);

        return RetentionPolicy::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'data_category' => $data['data_category'],
            'subject_type' => $data['subject_type'],
            'trigger_event' => RetentionTriggerEvent::from($data['trigger_event']),
            'retention_months' => $months,
            'strategy' => RetentionStrategy::from($data['strategy'] ?? 'anonymize'),
            'legal_basis_note' => $data['legal_basis_note'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
            'requires_approval' => (bool) ($data['requires_approval'] ?? true),
            'is_system_draft' => false,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RetentionPolicy $policy, User $actor, array $data): RetentionPolicy
    {
        if (array_key_exists('retention_months', $data)) {
            $this->assertRetentionMonths((int) $data['retention_months']);
        }

        $fill = collect($data)->only([
            'name', 'data_category', 'subject_type', 'trigger_event',
            'retention_months', 'strategy', 'legal_basis_note',
            'active', 'requires_approval',
        ])->all();

        if (isset($fill['trigger_event'])) {
            $fill['trigger_event'] = RetentionTriggerEvent::from($fill['trigger_event']);
        }
        if (isset($fill['strategy'])) {
            $fill['strategy'] = RetentionStrategy::from($fill['strategy']);
        }
        $fill['updated_by'] = $actor->id;
        $policy->forceFill($fill)->save();

        return $policy->fresh();
    }

    /**
     * TR tipik politikalar — PASİF taslak. Seed asla otomatik aktif etmez.
     *
     * @return list<RetentionPolicy>
     */
    public function seedDraftsForCompany(int $companyId): array
    {
        $drafts = [
            [
                'name' => 'Özlük dosyası (ayrılan personel)',
                'data_category' => 'identity',
                'subject_type' => 'former_employee',
                'trigger_event' => RetentionTriggerEvent::IstenAyrilma,
                'retention_months' => 120,
                'strategy' => RetentionStrategy::Anonymize,
                'legal_basis_note' => 'İş Kanunu / SGK saklama — taslak; firma aktifleştirir',
            ],
            [
                'name' => 'Reddedilen aday CV',
                'data_category' => 'cv_recruitment',
                'subject_type' => 'candidate',
                'trigger_event' => RetentionTriggerEvent::BasvuruReddi,
                'retention_months' => 6,
                'strategy' => RetentionStrategy::Anonymize,
                'legal_basis_note' => 'İşe alım süreci sonu — taslak',
            ],
            [
                'name' => 'PDKS ham kayıt',
                'data_category' => 'location',
                'subject_type' => 'employee',
                'trigger_event' => RetentionTriggerEvent::KayitTarihi,
                'retention_months' => 24,
                'strategy' => RetentionStrategy::Anonymize,
                'legal_basis_note' => 'PDKS — taslak',
            ],
            [
                'name' => 'İletişim / konum (kamera)',
                'data_category' => 'location',
                'subject_type' => 'visitor',
                'trigger_event' => RetentionTriggerEvent::KayitTarihi,
                'retention_months' => 3,
                'strategy' => RetentionStrategy::HardDelete,
                'legal_basis_note' => 'Kamera kaydı — taslak; hard_delete yalnız onayla',
            ],
            [
                'name' => 'Sağlık / iş kazası',
                'data_category' => 'health_special',
                'subject_type' => 'employee',
                'trigger_event' => RetentionTriggerEvent::KayitTarihi,
                'retention_months' => 180,
                'strategy' => RetentionStrategy::Anonymize,
                'legal_basis_note' => 'İş sağlığı mevzuatı — taslak',
            ],
        ];

        $created = [];
        foreach ($drafts as $d) {
            $created[] = RetentionPolicy::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'name' => $d['name'],
                    'is_system_draft' => true,
                ],
                [
                    'data_category' => $d['data_category'],
                    'subject_type' => $d['subject_type'],
                    'trigger_event' => $d['trigger_event'],
                    'retention_months' => $d['retention_months'],
                    'strategy' => $d['strategy'],
                    'legal_basis_note' => $d['legal_basis_note'],
                    'active' => false, // ASLA otomatik aktif
                    'requires_approval' => true,
                    'is_system_draft' => true,
                ]
            );
        }

        return $created;
    }
}
