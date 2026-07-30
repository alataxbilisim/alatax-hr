<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\SurveySubmission;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class SurveyCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'survey';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.survey';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(SurveySubmission::class)) {
            return [];
        }

        try {
            $records = SurveySubmission::query()
                ->where('user_id', $userId)
                ->whereNotNull('user_id')
                ->get(['id', 'survey_id', 'status', 'started_at', 'completed_at'])
                ->map(fn ($s) => array_merge($s->toArray(), ['source' => 'survey_submissions']))
                ->all();
        } catch (\Throwable) {
            return [];
        }

        // Anonim gönderimler (user_id null) dahil edilmez.
        return $records === [] ? [] : [[
            'category' => 'survey',
            'label' => 'Anket yanıtları (anonim gönderimler hariç)',
            'records' => $records,
            'files' => [],
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        // D2c: istatistik kay�tlar� kal�r; kimlik alanlar� �st collector (employee_profile) maskeler.
        // Bu collector i�in ek PII yoksa no-op; dry-run da ayn� sonucu d�ner.
        return \App\Services\Kvkk\PersonalData\AnonymizationHelper::emptyResult();
    }
}
