<?php

namespace App\Services\Kvkk;

use App\Enums\KvkkLegalBasis;
use App\Models\DataProcessingActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataProcessingActivityService
{
    /**
     * TR İK tipik faaliyet taslakları — hukuki metin boş/placeholder; firma doldurur.
     * On-prem: transfer_abroad=false + müşteri sunucusu notu.
     *
     * @return list<array<string, mixed>>
     */
    public function templates(): array
    {
        $onPremNote = 'Veriler müşteri sunucusunda işlenir (on-prem / tek kiracı); yurt dışı aktarım varsayılan kapalı.';

        return [
            [
                'key' => 'personnel_file',
                'name' => 'Özlük yönetimi',
                'data_categories' => ['identity', 'contact', 'family_emergency'],
                'purpose' => null,
                'legal_basis' => KvkkLegalBasis::Contract->value,
                'data_subject_group' => 'employee',
                'recipients' => [],
                'retention_period_months' => null,
                'transfer_abroad' => false,
                'transfer_abroad_note' => $onPremNote,
                'security_measures' => null,
            ],
            [
                'key' => 'payroll',
                'name' => 'Ücret / bordro işlemleri',
                'data_categories' => ['identity', 'financial'],
                'purpose' => null,
                'legal_basis' => KvkkLegalBasis::LegalObligation->value,
                'data_subject_group' => 'employee',
                'recipients' => [],
                'retention_period_months' => null,
                'transfer_abroad' => false,
                'transfer_abroad_note' => $onPremNote,
                'security_measures' => null,
            ],
            [
                'key' => 'recruitment',
                'name' => 'İşe alım süreçleri',
                'data_categories' => ['identity', 'contact', 'cv_recruitment'],
                'purpose' => null,
                'legal_basis' => KvkkLegalBasis::ExplicitConsent->value,
                'data_subject_group' => 'candidate',
                'recipients' => [],
                'retention_period_months' => null,
                'transfer_abroad' => false,
                'transfer_abroad_note' => $onPremNote,
                'security_measures' => null,
            ],
            [
                'key' => 'timesheet_pdks',
                'name' => 'PDKS / devam takibi',
                'data_categories' => ['identity', 'location'],
                'purpose' => null,
                'legal_basis' => KvkkLegalBasis::LegitimateInterest->value,
                'data_subject_group' => 'employee',
                'recipients' => [],
                'retention_period_months' => null,
                'transfer_abroad' => false,
                'transfer_abroad_note' => $onPremNote,
                'security_measures' => null,
            ],
            [
                'key' => 'ohs_health',
                'name' => 'İSG sağlık gözetimi',
                'data_categories' => ['health_special', 'identity'],
                'purpose' => null,
                'legal_basis' => KvkkLegalBasis::LegalObligation->value,
                'data_subject_group' => 'employee',
                'recipients' => [],
                'retention_period_months' => null,
                'transfer_abroad' => false,
                'transfer_abroad_note' => $onPremNote,
                'security_measures' => null,
            ],
            [
                'key' => 'leave_management',
                'name' => 'İzin yönetimi',
                'data_categories' => ['identity', 'health_special'],
                'purpose' => null,
                'legal_basis' => KvkkLegalBasis::Contract->value,
                'data_subject_group' => 'employee',
                'recipients' => [],
                'retention_period_months' => null,
                'transfer_abroad' => false,
                'transfer_abroad_note' => $onPremNote,
                'security_measures' => null,
            ],
            [
                'key' => 'surveys',
                'name' => 'Anket / geri bildirim',
                'data_categories' => ['survey_answers'],
                'purpose' => null,
                'legal_basis' => KvkkLegalBasis::LegitimateInterest->value,
                'data_subject_group' => 'employee',
                'recipients' => [],
                'retention_period_months' => null,
                'transfer_abroad' => false,
                'transfer_abroad_note' => $onPremNote,
                'security_measures' => null,
            ],
        ];
    }

    /**
     * Firma için taslakları firstOrCreate — mevcut özelleştirilmiş kayıtları ezmez.
     */
    public function ensureDefaultsForCompany(int $companyId): void
    {
        foreach ($this->templates() as $tpl) {
            DataProcessingActivity::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'key' => $tpl['key'],
                ],
                array_merge($tpl, [
                    'company_id' => $companyId,
                    'is_system' => true,
                ])
            );
        }
    }

    public function listFor(int $companyId, int $perPage = 50): LengthAwarePaginator
    {
        $this->ensureDefaultsForCompany($companyId);

        return DataProcessingActivity::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $companyId, array $data): DataProcessingActivity
    {
        $this->assertCategories($data['data_categories'] ?? []);

        return DataProcessingActivity::create([
            'company_id' => $companyId,
            'key' => $data['key'],
            'name' => $data['name'],
            'data_categories' => $data['data_categories'] ?? [],
            'purpose' => $data['purpose'] ?? null,
            'legal_basis' => $data['legal_basis'],
            'data_subject_group' => $data['data_subject_group'],
            'recipients' => $data['recipients'] ?? [],
            'retention_period_months' => $data['retention_period_months'] ?? null,
            'transfer_abroad' => (bool) ($data['transfer_abroad'] ?? false),
            'transfer_abroad_note' => $data['transfer_abroad_note'] ?? null,
            'security_measures' => $data['security_measures'] ?? null,
            'is_system' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DataProcessingActivity $activity, array $data): DataProcessingActivity
    {
        if (array_key_exists('data_categories', $data)) {
            $this->assertCategories($data['data_categories'] ?? []);
        }
        $activity->fill(collect($data)->only([
            'name', 'data_categories', 'purpose', 'legal_basis', 'data_subject_group',
            'recipients', 'retention_period_months', 'transfer_abroad',
            'transfer_abroad_note', 'security_measures',
        ])->all());
        $activity->save();

        return $activity->fresh();
    }

    public function delete(DataProcessingActivity $activity): void
    {
        $activity->delete();
    }

    /**
     * VERBİS hazırlığı — CSV (Excel uyumlu). PhpSpreadsheet bağımlılığı yok.
     */
    public function exportExcel(int $companyId): StreamedResponse
    {
        $this->ensureDefaultsForCompany($companyId);
        /** @var Collection<int, DataProcessingActivity> $rows */
        $rows = DataProcessingActivity::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $filename = 'kvkk_veri_envanteri_'.$companyId.'_'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // Excel UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Anahtar', 'Ad', 'Kategoriler', 'Amaç', 'Hukuki sebep', 'Veri sahibi grubu',
                'Alıcılar', 'Saklama (ay)', 'Yurt dışı', 'Yurt dışı notu', 'Güvenlik önlemleri',
            ], ';');
            foreach ($rows as $row) {
                $cats = is_array($row->data_categories) ? implode(', ', $row->data_categories) : '';
                $recipients = is_array($row->recipients) ? implode(', ', $row->recipients) : '';
                fputcsv($out, [
                    $row->key,
                    $row->name,
                    $cats,
                    $row->purpose ?? '',
                    $row->legal_basis instanceof KvkkLegalBasis
                        ? $row->legal_basis->value
                        : (string) $row->legal_basis,
                    $row->data_subject_group,
                    $recipients,
                    $row->retention_period_months,
                    $row->transfer_abroad ? 'evet' : 'hayır',
                    $row->transfer_abroad_note ?? '',
                    $row->security_measures ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function assertCategories(mixed $categories): void
    {
        if (! is_array($categories)) {
            throw ValidationException::withMessages([
                'data_categories' => ['Veri kategorileri dizi olmalıdır.'],
            ]);
        }
        $allowed = KvkkDataCategoryCatalog::keys();
        foreach ($categories as $c) {
            if (! is_string($c) || ! in_array($c, $allowed, true)) {
                throw ValidationException::withMessages([
                    'data_categories' => ['Geçersiz veri kategorisi: '.(is_string($c) ? $c : '—')],
                ]);
            }
        }
    }
}
