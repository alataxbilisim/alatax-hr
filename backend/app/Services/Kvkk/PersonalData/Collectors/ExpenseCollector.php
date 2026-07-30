<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\ExpenseClaim;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;

final class ExpenseCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'expense';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.expense';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId || ! class_exists(ExpenseClaim::class)) {
            return [];
        }

        $claims = ExpenseClaim::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->with('items')
            ->get();

        $records = [];
        $files = [];
        foreach ($claims as $claim) {
            $records[] = [
                'source' => 'expense_claims',
                'id' => $claim->id,
                'title' => $claim->title,
                'claim_number' => $claim->claim_number,
                'expense_date' => $claim->expense_date,
                'total_amount' => $claim->total_amount,
                'currency' => $claim->currency,
                'status' => $claim->status,
            ];
            foreach ($claim->items ?? [] as $item) {
                if (filled($item->receipt_path ?? null)) {
                    $files[] = [
                        'path' => (string) $item->receipt_path,
                        'name' => (string) ($item->receipt_number ?: 'receipt_'.$item->id),
                    ];
                }
            }
        }

        return $records === [] ? [] : [[
            'category' => 'expense',
            'label' => 'Masraf talepleri',
            'records' => $records,
            'files' => $files,
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        // D2c: istatistik kayıtları kalır; kimlik alanları üst collector (employee_profile) maskeler.
        // Bu collector için ek PII yoksa no-op; dry-run da aynı sonucu döner.
        return \App\Services\Kvkk\PersonalData\AnonymizationHelper::emptyResult();
    }
}
