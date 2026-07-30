<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class LeaveCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'leave';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.leave';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(LeaveRequest::class)) {
            return [];
        }

        $requests = LeaveRequest::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->get(['id', 'leave_type_id', 'start_date', 'end_date', 'total_days', 'reason', 'status', 'document_path', 'document_name']);

        $balances = class_exists(LeaveBalance::class)
            ? LeaveBalance::query()
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->get(['id', 'leave_type_id', 'year', 'total_days', 'used_days', 'pending_days', 'carried_over'])
                ->map(fn ($b) => array_merge($b->toArray(), ['source' => 'leave_balances']))
                ->all()
            : [];

        $records = array_merge(
            $requests->map(fn ($r) => [
                'source' => 'leave_requests',
                'id' => $r->id,
                'leave_type_id' => $r->leave_type_id,
                'start_date' => $r->start_date,
                'end_date' => $r->end_date,
                'total_days' => $r->total_days,
                'reason' => $r->reason,
                'status' => $r->status,
            ])->all(),
            $balances
        );

        $files = $requests
            ->filter(fn ($r) => filled($r->document_path))
            ->map(fn ($r) => ['path' => (string) $r->document_path, 'name' => (string) ($r->document_name ?: 'leave_document')])
            ->values()
            ->all();

        return $records === [] ? [] : [[
            'category' => 'leave',
            'label' => 'İzin talepleri ve bakiyeleri',
            'records' => $records,
            'files' => $files,
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        // D2c: istatistik kay�tlar� kal�r; kimlik alanlar� �st collector (employee_profile) maskeler.
        // Bu collector i�in ek PII yoksa no-op; dry-run da ayn� sonucu d�ner.
        return \App\Services\Kvkk\PersonalData\AnonymizationHelper::emptyResult();
    }
}
