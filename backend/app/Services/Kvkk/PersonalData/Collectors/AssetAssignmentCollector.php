<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\AssetAssignment;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class AssetAssignmentCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'asset_assignment';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.assetAssignment';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(AssetAssignment::class)) {
            return [];
        }

        try {
            $records = AssetAssignment::query()
                ->where('user_id', $userId)
                ->get(['id', 'asset_id', 'assigned_date', 'return_date', 'notes', 'condition_at_assignment', 'condition_at_return'])
                ->map(fn ($a) => array_merge($a->toArray(), ['source' => 'asset_assignments']))
                ->all();
        } catch (\Throwable) {
            return [];
        }

        return $records === [] ? [] : [[
            'category' => 'assets',
            'label' => 'Zimmet atamaları',
            'records' => $records,
            'files' => [],
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        // D2c: istatistik kayıtları kalır; kimlik alanları üst collector (employee_profile) maskeler.
        // Bu collector için ek PII yoksa no-op; dry-run da aynı sonucu döner.
        return \App\Services\Kvkk\PersonalData\AnonymizationHelper::emptyResult();
    }
}
