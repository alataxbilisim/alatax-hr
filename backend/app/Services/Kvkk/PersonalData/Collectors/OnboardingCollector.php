<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\OnboardingProcess;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class OnboardingCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'onboarding';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.onboarding';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(OnboardingProcess::class)) {
            return [];
        }

        $records = OnboardingProcess::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->get([
                'id', 'process_type', 'title', 'start_date', 'target_end_date',
                'actual_end_date', 'status', 'progress', 'notes',
                'termination_reason_code', 'termination_date',
            ])
            ->map(fn ($p) => array_merge($p->toArray(), ['source' => 'onboarding_processes']))
            ->all();

        return $records === [] ? [] : [[
            'category' => 'onboarding',
            'label' => 'Onboarding / offboarding süreçleri',
            'records' => $records,
            'files' => [],
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        // D2c: istatistik kayıtları kalır; kimlik alanları üst collector (employee_profile) maskeler.
        // Bu collector için ek PII yoksa no-op; dry-run da aynı sonucu döner.
        return \App\Services\Kvkk\PersonalData\AnonymizationHelper::emptyResult();
    }
}
