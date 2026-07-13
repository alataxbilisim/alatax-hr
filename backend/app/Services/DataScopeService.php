<?php

namespace App\Services;

use App\Enums\DataScopeLevel;
use App\Enums\UserType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kullanıcının efektif veri kapsamını çözer ve Eloquent sorgularını filtreler.
 *
 * team = Employee.manager_id zinciri (kullanıcının employee kaydına bağlı subordinates).
 * department = aynı Employee.department_id.
 * branch = aynı Employee.branch_id (şube yöneticisi).
 * own = user_id / employee kendi kaydı.
 * company = ek filtre yok (BelongsToCompany zaten firma sınırı koyar).
 */
class DataScopeService
{
    /**
     * Birden fazla rolde en geniş kapsam kazanır.
     * company_admin / super_admin → company (firma geneli liste filtresi).
     * Rol yoksa → own (en dar güvenli varsayılan).
     */
    public function resolve(User $user): DataScopeLevel
    {
        if ($user->type === UserType::SuperAdmin || $user->type === UserType::CompanyAdmin) {
            return DataScopeLevel::Company;
        }

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
     * user_id kolonuna sahip modeller için kapsam filtresi (LeaveRequest, ExpenseClaim vb.).
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
            DataScopeLevel::Branch => $query->whereIn($userIdColumn, $this->branchUserIds($user)),
        };
    }

    /**
     * Employee tablosu: id / manager_id / department_id / branch_id üzerinden filtre.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function scopeForEmployee(Builder $query, User $user): Builder
    {
        $scope = $this->resolve($user);

        return match ($scope) {
            DataScopeLevel::Company => $query,
            DataScopeLevel::Own => $query->whereIn('id', $this->ownEmployeeIds($user)),
            DataScopeLevel::Team => $query->whereIn('id', $this->teamEmployeeIds($user)),
            DataScopeLevel::Department => $this->applyDepartmentEmployeeScope($query, $user),
            DataScopeLevel::Branch => $this->applyBranchEmployeeScope($query, $user),
        };
    }

    /**
     * PerformanceReview: employee_id ve reviewer_id → users.id.
     * Actor reviewee veya reviewer ise her zaman görür; ayrıca DataScope ile reviewee kapsamı.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function scopeForPerformanceReview(Builder $query, User $user): Builder
    {
        $scope = $this->resolve($user);

        if ($scope === DataScopeLevel::Company) {
            return $query;
        }

        $userIds = match ($scope) {
            DataScopeLevel::Own => [$user->id],
            DataScopeLevel::Team => $this->teamUserIds($user),
            DataScopeLevel::Department => $this->departmentUserIds($user),
            DataScopeLevel::Branch => $this->branchUserIds($user),
            default => [$user->id],
        };

        return $query->where(function (Builder $q) use ($user, $userIds) {
            $q->where('reviewer_id', $user->id);
            if ($userIds !== []) {
                $q->orWhereIn('employee_id', $userIds);
            }
        });
    }

    /**
     * Kayıt, kullanıcının user_id kapsamına giriyor mu?
     */
    public function allowsUserId(User $user, int $targetUserId): bool
    {
        $scope = $this->resolve($user);

        return match ($scope) {
            DataScopeLevel::Company => true,
            DataScopeLevel::Own => $targetUserId === $user->id,
            DataScopeLevel::Team => in_array($targetUserId, $this->teamUserIds($user), true),
            DataScopeLevel::Department => in_array($targetUserId, $this->departmentUserIds($user), true),
            DataScopeLevel::Branch => in_array($targetUserId, $this->branchUserIds($user), true),
        };
    }

    /**
     * Employee kaydı, kullanıcının kapsamına giriyor mu?
     */
    public function allowsEmployee(User $user, Employee $employee): bool
    {
        $scope = $this->resolve($user);

        return match ($scope) {
            DataScopeLevel::Company => true,
            DataScopeLevel::Own => in_array($employee->id, $this->ownEmployeeIds($user), true),
            DataScopeLevel::Team => in_array($employee->id, $this->teamEmployeeIds($user), true),
            DataScopeLevel::Department => $this->isSameDepartment($user, $employee),
            DataScopeLevel::Branch => $this->isSameBranch($user, $employee),
        };
    }

    /**
     * update/delete için: company veya department (İK); team/own yazamaz.
     */
    public function canManageHrRecords(User $user): bool
    {
        $scope = $this->resolve($user);

        return in_array($scope, [DataScopeLevel::Company, DataScopeLevel::Department], true);
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
     * Team: kendi employee id + doğrudan subordinate employee id'leri.
     *
     * @return list<int>
     */
    public function teamEmployeeIds(User $user): array
    {
        $employee = $this->employeeFor($user);

        if (! $employee) {
            return [];
        }

        $subordinateIds = Employee::query()
            ->where('manager_id', $employee->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge([$employee->id], $subordinateIds)));
    }

    /**
     * @return list<int>
     */
    public function ownEmployeeIds(User $user): array
    {
        $employee = $this->employeeFor($user);

        return $employee ? [$employee->id] : [];
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
     * Branch: aynı branch_id'deki personellerin user_id'leri (+ kendi).
     *
     * @return list<int>
     */
    public function branchUserIds(User $user): array
    {
        $employee = $this->employeeFor($user);

        if (! $employee || ! $employee->branch_id) {
            return [$user->id];
        }

        $ids = Employee::query()
            ->where('branch_id', $employee->branch_id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge([$user->id], $ids)));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyBranchEmployeeScope(Builder $query, User $user): Builder
    {
        $employee = $this->employeeFor($user);

        if (! $employee || ! $employee->branch_id) {
            return $query->whereIn('id', $this->ownEmployeeIds($user));
        }

        return $query->where('branch_id', $employee->branch_id);
    }

    protected function isSameBranch(User $user, Employee $target): bool
    {
        $employee = $this->employeeFor($user);

        if (! $employee || ! $employee->branch_id || ! $target->branch_id) {
            return in_array($target->id, $this->ownEmployeeIds($user), true);
        }

        return (int) $employee->branch_id === (int) $target->branch_id;
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

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyDepartmentEmployeeScope(Builder $query, User $user): Builder
    {
        $employee = $this->employeeFor($user);

        if (! $employee || ! $employee->department_id) {
            return $query->whereIn('id', $this->ownEmployeeIds($user));
        }

        return $query->where('department_id', $employee->department_id);
    }

    protected function isSameDepartment(User $user, Employee $target): bool
    {
        $employee = $this->employeeFor($user);

        if (! $employee || ! $employee->department_id || ! $target->department_id) {
            return in_array($target->id, $this->ownEmployeeIds($user), true);
        }

        return (int) $employee->department_id === (int) $target->department_id;
    }

    protected function employeeFor(User $user): ?Employee
    {
        return $user->relationLoaded('employee')
            ? $user->employee
            : $user->employee()->first();
    }
}
