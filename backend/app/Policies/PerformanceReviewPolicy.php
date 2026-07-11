<?php

namespace App\Policies;

use App\Enums\DataScopeLevel;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\DataScopeService;

/**
 * PerformanceReview satır yetkisi.
 *
 * Şema notu: employee_id → users.id (reviewee), reviewer_id → users.id.
 * employees tablosu FK'si değil.
 *
 * view: reviewee | reviewer | DataScope (team/dept/company) ile reviewee.
 * update: yalnızca reviewer (approved hariç).
 * approve: company | team subordinate (reviewee) | department — legacy serbest yok.
 */
class PerformanceReviewPolicy
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PerformanceReview $review): bool
    {
        if ((int) $review->employee_id === $user->id || (int) $review->reviewer_id === $user->id) {
            return true;
        }

        return $this->dataScope->allowsUserId($user, (int) $review->employee_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PerformanceReview $review): bool
    {
        if ($review->status === 'approved') {
            return false;
        }

        // Yalnızca reviewer kendi değerlendirmesini düzenler
        return (int) $review->reviewer_id === $user->id;
    }

    public function delete(User $user, PerformanceReview $review): bool
    {
        if ($review->status === 'approved') {
            return false;
        }

        if ((int) $review->reviewer_id === $user->id && $review->status === 'draft') {
            return true;
        }

        return $this->dataScope->resolve($user) === DataScopeLevel::Company;
    }

    public function approve(User $user, PerformanceReview $review): bool
    {
        $scope = $this->dataScope->resolve($user);

        if ($scope === DataScopeLevel::Company) {
            return true;
        }

        if ($scope === DataScopeLevel::Team) {
            return $this->dataScope->isDirectSubordinate($user, (int) $review->employee_id);
        }

        if ($scope === DataScopeLevel::Department) {
            return $this->dataScope->allowsUserId($user, (int) $review->employee_id)
                && (int) $review->employee_id !== $user->id;
        }

        return false;
    }
}
