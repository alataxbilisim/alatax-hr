<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Survey;
use App\Models\User;

/**
 * Anket hedef kitle eşlemesi (audience + audience_filter).
 */
class SurveyAudienceService
{
    public function userMatches(Survey $survey, User $user): bool
    {
        $audience = $survey->audience ?: 'all';

        if ($audience === 'all') {
            return true;
        }

        $employee = Employee::where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->first();

        if (! $employee) {
            return false;
        }

        $filter = is_array($survey->audience_filter) ? $survey->audience_filter : [];

        return match ($audience) {
            'department' => $this->matchesDepartment($employee, $filter),
            'position' => $this->matchesPosition($employee, $filter),
            'custom' => $this->matchesCustom($user, $filter),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function matchesDepartment(Employee $employee, array $filter): bool
    {
        $ids = $filter['department_ids'] ?? $filter['ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return false;
        }
        $ids = array_map('intval', $ids);

        return $employee->department_id !== null && in_array((int) $employee->department_id, $ids, true);
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function matchesPosition(Employee $employee, array $filter): bool
    {
        $codes = $filter['positions'] ?? $filter['position_codes'] ?? $filter['ids'] ?? [];
        if (! is_array($codes) || $codes === []) {
            return false;
        }
        $codes = array_map('strval', $codes);

        return $employee->position !== null && in_array((string) $employee->position, $codes, true);
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function matchesCustom(User $user, array $filter): bool
    {
        $ids = $filter['user_ids'] ?? $filter['ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return false;
        }
        $ids = array_map('intval', $ids);

        return in_array((int) $user->id, $ids, true);
    }
}
