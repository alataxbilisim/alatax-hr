<?php

namespace App\Policies;

use App\Enums\DataScopeLevel;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\DataScopeService;

/**
 * Attendance satır yetkisi — ExpenseClaim / Leave ile aynı DataScope modeli.
 */
class AttendancePolicy
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->dataScope->allowsUserId($user, (int) $record->user_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return $this->dataScope->allowsUserId($user, (int) $record->user_id);
    }

    public function approve(User $user, AttendanceRecord $record): bool
    {
        $scope = $this->dataScope->resolve($user);

        if ($scope === DataScopeLevel::Company) {
            return true;
        }

        if ($scope === DataScopeLevel::Team) {
            return $this->dataScope->isDirectSubordinate($user, (int) $record->user_id);
        }

        if ($scope === DataScopeLevel::Department || $scope === DataScopeLevel::Branch) {
            return $this->dataScope->allowsUserId($user, (int) $record->user_id)
                && (int) $record->user_id !== $user->id;
        }

        return false;
    }
}
