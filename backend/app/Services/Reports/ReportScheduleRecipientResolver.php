<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * D1f — Zamanlama alıcılarını User listesine çözer (max 50).
 */
final class ReportScheduleRecipientResolver
{
    /**
     * @param  list<array<string, mixed>>  $recipients
     * @return Collection<int, User>
     */
    public function resolve(int $companyId, array $recipients): Collection
    {
        $users = collect();
        foreach ($recipients as $r) {
            if (! is_array($r)) {
                continue;
            }
            $userId = isset($r['user_id']) ? (int) $r['user_id'] : null;
            $roleId = isset($r['role_id']) ? (int) $r['role_id'] : null;
            $deptId = isset($r['department_id']) ? (int) $r['department_id'] : null;
            $targets = (int) (bool) $userId + (int) (bool) $roleId + (int) (bool) $deptId;
            if ($targets !== 1) {
                continue;
            }

            if ($userId) {
                $u = User::query()->where('company_id', $companyId)->whereKey($userId)->first();
                if ($u) {
                    $users->push($u);
                }

                continue;
            }

            if ($roleId) {
                $role = Role::query()->whereKey($roleId)->first();
                if (! $role) {
                    continue;
                }
                $roleUsers = User::query()
                    ->where('company_id', $companyId)
                    ->role($role->name)
                    ->get();
                foreach ($roleUsers as $u) {
                    $users->push($u);
                }

                continue;
            }

            if ($deptId) {
                $empUserIds = Employee::query()
                    ->where('company_id', $companyId)
                    ->where('department_id', $deptId)
                    ->whereNotNull('user_id')
                    ->pluck('user_id');
                $deptUsers = User::query()
                    ->where('company_id', $companyId)
                    ->whereIn('id', $empUserIds)
                    ->get();
                foreach ($deptUsers as $u) {
                    $users->push($u);
                }
            }
        }

        return $users->unique('id')->values();
    }

    /**
     * @param  list<array<string, mixed>>  $recipients
     */
    public function assertWithinLimit(int $companyId, array $recipients): Collection
    {
        $resolved = $this->resolve($companyId, $recipients);
        if ($resolved->count() > ReportSchedule::MAX_RECIPIENTS) {
            throw ValidationException::withMessages([
                'recipients' => [
                    'Alıcı sayısı '.ReportSchedule::MAX_RECIPIENTS.' üst sınırını aşıyor; rolü veya departmanı daraltın.',
                ],
            ]);
        }

        return $resolved;
    }
}
