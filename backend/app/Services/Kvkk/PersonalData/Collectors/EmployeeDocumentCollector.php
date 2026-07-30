<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\EmployeeDocument;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class EmployeeDocumentCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'employee_document';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.employeeDocument';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $employeeId = $this->resolveEmployeeId($subjectType, $subjectId, $companyId);
        if (! $employeeId || ! class_exists(EmployeeDocument::class)) {
            return [];
        }

        $rows = EmployeeDocument::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->get(['id', 'title', 'category', 'file_path', 'file_name', 'issue_date', 'expiry_date', 'status']);

        $records = $rows->map(fn ($d) => [
            'source' => 'employee_documents',
            'id' => $d->id,
            'title' => $d->title,
            'category' => $d->category,
            'issue_date' => $d->issue_date,
            'expiry_date' => $d->expiry_date,
            'status' => $d->status,
        ])->all();

        $files = $rows
            ->filter(fn ($d) => filled($d->file_path))
            ->map(fn ($d) => ['path' => (string) $d->file_path, 'name' => (string) ($d->file_name ?: 'document_'.$d->id)])
            ->values()
            ->all();

        return $records === [] ? [] : [[
            'category' => 'documents',
            'label' => 'Personel belgeleri',
            'records' => $records,
            'files' => $files,
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        $employeeId = $this->resolveEmployeeId($subjectType, $subjectId, $companyId);
        if (! $employeeId) {
            return \App\Services\Kvkk\PersonalData\AnonymizationHelper::emptyResult();
        }

        $rows = EmployeeDocument::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->get();

        $files = [];
        foreach ($rows as $d) {
            if ($d->file_path) {
                $files[] = (string) $d->file_path;
            }
            if (! $dryRun) {
                if ($d->file_path) {
                    try {
                        \Illuminate\Support\Facades\Storage::disk('private')->delete((string) $d->file_path);
                    } catch (\Throwable) {
                    }
                }
                $d->forceFill([
                    'file_path' => null,
                    'file_name' => null,
                    'title' => 'Anonim belge',
                ])->save();
            }
        }

        return \App\Services\Kvkk\PersonalData\AnonymizationHelper::result(
            $rows->count(),
            ['file_path', 'file_name', 'title'],
            $files,
            ['employee_documents']
        );
    }
}
