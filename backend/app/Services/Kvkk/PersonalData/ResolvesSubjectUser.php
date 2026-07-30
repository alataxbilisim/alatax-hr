<?php

namespace App\Services\Kvkk\PersonalData;

use App\Models\Employee;

trait ResolvesSubjectUser
{
    protected function resolveUserId(string $subjectType, int $subjectId, int $companyId): ?int
    {
        if (in_array($subjectType, ['employee', 'former_employee'], true)) {
            $emp = Employee::query()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($subjectId) {
                    $q->where('id', $subjectId)->orWhere('user_id', $subjectId);
                })
                ->first();
            if ($emp?->user_id) {
                return (int) $emp->user_id;
            }
        }

        return $subjectId > 0 ? $subjectId : null;
    }

    protected function resolveEmployeeId(string $subjectType, int $subjectId, int $companyId): ?int
    {
        $emp = Employee::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($subjectId) {
                $q->where('id', $subjectId)->orWhere('user_id', $subjectId);
            })
            ->first();

        return $emp ? (int) $emp->id : null;
    }
}
