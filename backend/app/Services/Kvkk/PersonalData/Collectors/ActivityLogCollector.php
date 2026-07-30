<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\ActivityLog;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class ActivityLogCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'activity_log';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.activityLog';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(ActivityLog::class)) {
            return [];
        }

        $records = ActivityLog::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'action', 'description', 'old_values', 'new_values', 'created_at'])
            ->map(fn ($log) => [
                'source' => 'activity_logs',
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at,
                'old_values' => $this->truncate($log->old_values),
                'new_values' => $this->truncate($log->new_values),
            ])
            ->all();

        return $records === [] ? [] : [[
            'category' => 'activity',
            'label' => 'Aktivite logları',
            'records' => $records,
            'files' => [],
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        // Log satırı silinmez — aktör adı maskelenir (denetim izi korunur).
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId) {
            return \App\Services\Kvkk\PersonalData\AnonymizationHelper::emptyResult();
        }

        $q = ActivityLog::query()->where('company_id', $companyId)->where('user_id', $userId);
        $count = (clone $q)->count();
        $label = \App\Services\Kvkk\PersonalData\AnonymizationHelper::anonymLabel($userId);
        if (! $dryRun && $count > 0) {
            $q->update([
                'user_name' => $label,
                'ip_address' => null,
                'user_agent' => null,
            ]);
        }

        return \App\Services\Kvkk\PersonalData\AnonymizationHelper::result(
            $count,
            ['user_name', 'ip_address', 'user_agent'],
            [],
            ['activity_logs']
        );
    }

    /** @param  array<string, mixed>|string|null  $value */
    private function truncate(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return null;
        }

        return strlen($json) > 500 ? substr($json, 0, 500).'…' : $value;
    }
}
