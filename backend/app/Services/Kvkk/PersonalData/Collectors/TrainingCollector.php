<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\TrainingParticipant;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class TrainingCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'training';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.training';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(TrainingParticipant::class)) {
            return [];
        }

        try {
            $records = TrainingParticipant::query()
                ->where('user_id', $userId)
                ->get(['id', 'session_id', 'status', 'score', 'passed', 'feedback', 'registered_at', 'completed_at'])
                ->map(fn ($p) => array_merge($p->toArray(), ['source' => 'training_participants']))
                ->all();
        } catch (\Throwable) {
            return [];
        }

        return $records === [] ? [] : [[
            'category' => 'training',
            'label' => 'Eğitim katılımları',
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
