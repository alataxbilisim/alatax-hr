<?php

namespace App\Services;

use App\Enums\DataScopeLevel;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kullanıcının efektif veri kapsamını çözer ve Eloquent sorgularını filtreler.
 *
 * team = Employee.manager_id zinciri (kullanıcının employee kaydına bağlı subordinates).
 * department = aynı Employee.department_id.
 * own = user_id = auth.
 * company = ek filtre yok (BelongsToCompany zaten firma sınırı koyar).
 * branch = henüz Employee.branch_id yok; LeaveRequest için boş küme.
 */
class DataScopeService
{
    /**
     * Birden fazla rolde en geniş kapsam kazanır.
     * Rol yoksa veya tanımsızsa → own (en dar güvenli varsayılan).
     */
    public function resolve(User $user): DataScopeLevel
    {
        $roles = $user->roles;

        if ($roles->isEmpty()) {
            return DataScopeLevel::Own;
        }

        $widest = DataScopeLevel::Own;

        foreach ($roles as $role) {
            $raw = $role->data_scope
                ?? config('data-scope.defaults.'.$role->name)
                ?? DataScopeLevel::Own->value;

            $level = DataScopeLevel::tryFrom((string) $raw) ?? DataScopeLevel::Own;

            if ($level->width() > $widest->width()) {
                $widest = $level;
            }
        }

        return $widest;
    }

    /**
     * user_id kolonuna sahip modeller için kapsam filtresi (LeaveRequest vb.).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function scopeForUser(Builder $query, User $user, string $userIdColumn = 'user_id'): Builder
    {
        $scope = $this->resolve($user);

        return match ($scope) {
            DataScopeLevel::Company => $query,
            DataScopeLevel::Own => $query->where($userIdColumn, $user->id),
            DataScopeLevel::Team => $query->whereIn($userIdColumn, $this->teamUserIds($user)),
            DataScopeLevel::Department => $query->whereIn($userIdColumn, $this->departmentUserIds($user)),
            DataScopeLevel::Branch => $query->whereRaw('0 = 1'), // branch_id henüz yok
        };
    }

    /**
     * Kayıt, kullanıcının kapsamına giriyor mu?
     */
    public function allowsUserId(User $user, int $targetUserId): bool
    {
        $scope = $this->resolve($user);

        return match ($scope) {
            DataScopeLevel::Company => true,
            DataScopeLevel::Own => $targetUserId === $user->id,
            DataScopeLevel::Team => in_array($targetUserId, $this->teamUserIds($user), true),
            DataScopeLevel::Department => in_array($targetUserId, $this->departmentUserIds($user), true),
            DataScopeLevel::Branch => false,
        };
    }

    /**
     * Team: kendi talebi + doğrudan subordinates'in user_id'leri.
     *
     * @return list<int>
     */
    public function teamUserIds(User $user): array
    {
        $employee = $this->employeeFor($user);

        if (! $employee) {
            return [$user->id];
        }

        $subordinateUserIds = Employee::query()
            ->where('manager_id', $employee->id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge([$user->id], $subordinateUserIds)));
    }

    /**
     * Department: aynı department_id'deki personellerin user_id'leri (+ kendi).
     *
     * @return list<int>
     */
    public function departmentUserIds(User $user): array
    {
        $employee = $this->employeeFor($user);

        if (! $employee || ! $employee->department_id) {
            return [$user->id];
        }

        $ids = Employee::query()
            ->where('department_id', $employee->department_id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge([$user->id], $ids)));
    }

    /**
     * Hedef kullanıcı, actor'ün doğrudan astı mı? (onay için; kendi talebi hariç)
     */
    public function isDirectSubordinate(User $manager, int $targetUserId): bool
    {
        if ($targetUserId === $manager->id) {
            return false;
        }

        $employee = $this->employeeFor($manager);

        if (! $employee) {
            return false;
        }

        return Employee::query()
            ->where('manager_id', $employee->id)
            ->where('user_id', $targetUserId)
            ->exists();
    }

    protected function employeeFor(User $user): ?Employee
    {
        return $user->relationLoaded('employee')
            ? $user->employee
            : $user->employee()->first();
    }
}
