<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\JobApplication;
use App\Models\User;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

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
        $employeeId = $this->resolveEmployeeId($subjectType, $subjectId, $companyId);
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if ((! $employeeId && ! $userId) || ! class_exists(JobApplication::class)) {
            return [];
        }

        $email = $userId
            ? User::query()->where('company_id', $companyId)->where('id', $userId)->value('email')
            : null;

        $rows = JobApplication::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($employeeId, $email) {
                if ($employeeId) {
                    $q->where('converted_employee_id', $employeeId);
                }
                if ($email) {
                    $employeeId ? $q->orWhere('email', $email) : $q->where('email', $email);
                }
            })
            ->get(['id', 'job_position_id', 'first_name', 'last_name', 'email', 'phone', 'cv_path', 'status', 'converted_employee_id', 'consent_kvkk', 'consent_at']);

        $records = $rows->map(fn ($a) => [
            'source' => 'job_applications',
            'id' => $a->id,
            'job_position_id' => $a->job_position_id,
            'first_name' => $a->first_name,
            'last_name' => $a->last_name,
            'email' => $a->email,
            'phone' => $a->phone,
            'status' => $a->status,
            'converted_employee_id' => $a->converted_employee_id,
        ])->all();

        $files = $rows
            ->filter(fn ($a) => filled($a->cv_path))
            ->map(fn ($a) => ['path' => (string) $a->cv_path, 'name' => 'cv_'.$a->id])
            ->values()
            ->all();

        return $records === [] ? [] : [[
            'category' => 'recruitment',
            'label' => 'İş başvuruları',
            'records' => $records,
            'files' => $files,
        ]];
    }

    public function destroy(int $subjectId, int $companyId, string $strategy): void
    {
        // D2c
    }
}
