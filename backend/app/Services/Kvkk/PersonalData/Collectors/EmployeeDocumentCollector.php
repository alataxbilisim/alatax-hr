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

    public function destroy(int $subjectId, int $companyId, string $strategy): void
    {
        // D2c
    }
}
