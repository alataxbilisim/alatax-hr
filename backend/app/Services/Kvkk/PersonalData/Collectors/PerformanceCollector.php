<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\PerformanceReview;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class PerformanceCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'performance';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.performance';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        // Bu kod tabanında performance_reviews.employee_id = users.id
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(PerformanceReview::class)) {
            return [];
        }

        $records = PerformanceReview::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $userId)
            ->get([
                'id', 'period_id', 'employee_id', 'reviewer_id', 'status',
                'overall_score', 'strengths', 'improvements', 'goals',
                'reviewer_comments', 'employee_comments', 'submitted_at', 'approved_at',
            ])
            ->map(fn ($r) => array_merge($r->toArray(), ['source' => 'performance_reviews']))
            ->all();

        return $records === [] ? [] : [[
            'category' => 'performance',
            'label' => 'Performans değerlendirmeleri',
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
