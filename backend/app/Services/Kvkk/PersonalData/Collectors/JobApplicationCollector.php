<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\JobApplication;
use App\Services\Kvkk\PersonalData\AnonymizationHelper;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;
use Illuminate\Support\Facades\Storage;

final class JobApplicationCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'job_application';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.jobApplication';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $rows = $this->queryApplications($subjectType, $subjectId, $companyId);
        if ($rows->isEmpty()) {
            return [];
        }

        $records = $rows->map(fn ($a) => [
            'source' => 'job_applications',
            'id' => $a->id,
            'job_position_id' => $a->job_position_id,
            'first_name' => $a->first_name,
            'last_name' => $a->last_name,
            'email' => $a->email,
            'phone' => $a->phone,
            'cv_path' => $a->cv_path,
            'status' => $a->status instanceof \BackedEnum ? $a->status->value : $a->status,
            'converted_employee_id' => $a->converted_employee_id,
            'consent_kvkk' => $a->consent_kvkk,
            'consent_at' => $a->consent_at,
        ])->all();

        $files = $rows->filter(fn ($a) => filled($a->cv_path))
            ->map(fn ($a) => ['path' => (string) $a->cv_path, 'name' => (string) ($a->cv_original_name ?? 'cv')])
            ->values()->all();

        return [[
            'category' => 'cv_recruitment',
            'label' => 'İş başvuruları',
            'records' => $records,
            'files' => $files,
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        $rows = $this->queryApplications($subjectType, $subjectId, $companyId);
        if ($rows->isEmpty()) {
            return AnonymizationHelper::emptyResult();
        }

        $fields = ['first_name', 'last_name', 'email', 'phone', 'cv_path', 'form_data', 'ip_address', 'notes'];
        $files = [];
        foreach ($rows as $a) {
            if ($a->cv_path) {
                $files[] = (string) $a->cv_path;
            }
            if (! $dryRun) {
                if ($a->cv_path) {
                    try {
                        Storage::disk('private')->delete((string) $a->cv_path);
                    } catch (\Throwable) {
                    }
                }
                $label = AnonymizationHelper::anonymLabel((int) $a->id);
                $a->forceFill([
                    'first_name' => $label,
                    'last_name' => 'Anonim',
                    'email' => 'anon-app-'.$a->id.'@invalid.local',
                    'phone' => null,
                    'cv_path' => null,
                    'cv_original_name' => null,
                    'form_data' => null,
                    'notes' => null,
                    'internal_notes' => null,
                    'ip_address' => null,
                    'user_agent' => null,
                    // status + job_position_id KALIR (istatistik)
                ])->save();
            }
        }

        return AnonymizationHelper::result($rows->count(), $fields, $files, ['job_applications']);
    }

    /** @return \Illuminate\Support\Collection<int, JobApplication> */
    private function queryApplications(string $subjectType, int $subjectId, int $companyId)
    {
        if ($subjectType === 'candidate') {
            return JobApplication::query()
                ->where('company_id', $companyId)
                ->where('id', $subjectId)
                ->get();
        }

        $employeeId = $this->resolveEmployeeId($subjectType, $subjectId, $companyId);
        if (! $employeeId) {
            return collect();
        }

        return JobApplication::query()
            ->where('company_id', $companyId)
            ->where('converted_employee_id', $employeeId)
            ->get();
    }
}
