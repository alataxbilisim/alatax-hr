<?php

namespace App\Services\Kvkk\PersonalData;

/**
 * Collector kayıt defteri — DatasetRegistry / SettingsRegistry deseni.
 */
class PersonalDataCollectorRegistry
{
    /** @var array<string, PersonalDataCollector>|null */
    private ?array $collectors = null;

    /**
     * @return array<string, PersonalDataCollector>
     */
    public function all(): array
    {
        if ($this->collectors === null) {
            $list = [
                new Collectors\EmployeeProfileCollector,
                new Collectors\LeaveCollector,
                new Collectors\AttendanceCollector,
                new Collectors\ExpenseCollector,
                new Collectors\PayslipCollector,
                new Collectors\EmployeeDocumentCollector,
                new Collectors\AssetAssignmentCollector,
                new Collectors\TrainingCollector,
                new Collectors\SurveyCollector,
                new Collectors\ConsentCollector,
                new Collectors\ActivityLogCollector,
                new Collectors\NotificationCollector,
                new Collectors\JobApplicationCollector,
                new Collectors\PerformanceCollector,
                new Collectors\OnboardingCollector,
            ];
            $this->collectors = [];
            foreach ($list as $c) {
                $this->collectors[$c->key()] = $c;
            }
        }

        return $this->collectors;
    }

    /**
     * @return list<array{
     *   collector: string,
     *   label_key: string,
     *   sections: list<array{category: string, label: string, records: list<array<string, mixed>>, files: list<array{path: string, name: string, category?: string}>}>
     * }>
     */
    public function collectAll(string $subjectType, int $subjectId, int $companyId): array
    {
        $out = [];
        foreach ($this->all() as $collector) {
            $sections = $collector->collect($subjectType, $subjectId, $companyId);
            if ($sections === []) {
                continue;
            }
            $out[] = [
                'collector' => $collector->key(),
                'label_key' => $collector->labelKey(),
                'sections' => $sections,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{collector: string, result: array{rows_affected: int, fields_masked: list<string>, files_deleted: list<string>, tables: list<string>}}>
     */
    public function destroyAll(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        $out = [];
        foreach ($this->all() as $collector) {
            $result = $collector->destroy($subjectType, $subjectId, $companyId, $strategy, $dryRun);
            if ($result['rows_affected'] === 0 && $result['files_deleted'] === [] && $result['tables'] === []) {
                continue;
            }
            $out[] = [
                'collector' => $collector->key(),
                'result' => $result,
            ];
        }

        return $out;
    }
}
