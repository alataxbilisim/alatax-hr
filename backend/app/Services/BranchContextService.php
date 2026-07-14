<?php

namespace App\Services;

use App\Enums\DataScopeLevel;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Şube bağlam seçici — DataScope İÇİNDE ek filtre; kapsamı genişletmez.
 */
class BranchContextService
{
    public const HEADER = 'X-Branch-Id';

    public const ALL = 'all';

    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    /**
     * @return array{branches: list<array{id: int, name: string, code: ?string}>, can_select_all: bool, locked_branch_id: ?int}
     */
    public function availableFor(User $user): array
    {
        $scope = $this->dataScope->resolve($user);
        $locked = $this->lockedBranchId($user, $scope);
        $canSelectAll = $scope === DataScopeLevel::Company;

        // branch scope: yalnız kendi şubesi (DataScope tavanı)
        if ($scope === DataScopeLevel::Branch && $locked !== null) {
            $branches = Branch::query()
                ->where('company_id', $user->company_id)
                ->where('id', $locked)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);

            return [
                'branches' => $branches->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'code' => $b->code,
                ])->values()->all(),
                'can_select_all' => false,
                'locked_branch_id' => $locked,
            ];
        }

        $branches = Branch::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return [
            'branches' => $branches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
            ])->values()->all(),
            'can_select_all' => $canSelectAll,
            'locked_branch_id' => null,
        ];
    }

    /**
     * Header / query'den bağlam çöz; geçersiz istekte 403.
     */
    public function resolveFromRequest(Request $request, User $user): BranchContext
    {
        $scope = $this->dataScope->resolve($user);
        $locked = $this->lockedBranchId($user, $scope);
        $raw = $request->header(self::HEADER) ?? $request->query('branch_id');
        $raw = is_string($raw) || is_numeric($raw) ? (string) $raw : null;

        // Branch scope: her zaman kilitli şube; "all" veya başka id → 403
        if ($scope === DataScopeLevel::Branch) {
            if ($locked === null) {
                return BranchContext::all(false);
            }
            if ($raw !== null && $raw !== '' && strtolower($raw) !== self::ALL && (int) $raw !== $locked) {
                ActivityLog::log(
                    'branch_context_denied',
                    $user,
                    "Şube bağlamı reddedildi: istenen={$raw}, kilitli={$locked}",
                    null,
                    ['requested' => $raw, 'locked_branch_id' => $locked],
                    false,
                    'branch_context_forbidden'
                );
                throw new HttpException(403, 'Bu şube için yetkiniz yok.');
            }

            return BranchContext::locked($locked);
        }

        $canSelectAll = $scope === DataScopeLevel::Company;

        if ($raw === null || $raw === '' || strtolower($raw) === self::ALL) {
            return $canSelectAll
                ? BranchContext::all(true)
                : ($locked !== null ? BranchContext::locked($locked) : BranchContext::all(false));
        }

        $branchId = (int) $raw;
        $exists = Branch::query()
            ->where('company_id', $user->company_id)
            ->where('id', $branchId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            ActivityLog::log(
                'branch_context_denied',
                $user,
                "Geçersiz şube bağlamı: {$branchId}",
                null,
                ['requested' => $branchId],
                false,
                'branch_context_invalid'
            );
            throw new HttpException(403, 'Geçersiz şube seçimi.');
        }

        if (! $canSelectAll && $locked !== null && $branchId !== $locked) {
            throw new HttpException(403, 'Bu şube için yetkiniz yok.');
        }

        return new BranchContext($branchId, $canSelectAll, $locked);
    }

    /**
     * Employee (veya branch_id kolonu olan) sorgulara ek filtre.
     * DataScope uygulandıktan SONRA çağrılmalı; "all" → no-op.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function applyToQuery(Builder $query, ?BranchContext $context = null, string $column = 'branch_id'): Builder
    {
        $context ??= app()->bound(BranchContext::class) ? app(BranchContext::class) : null;
        if ($context === null || $context->isAll()) {
            return $query;
        }

        return $query->where($column, $context->branchId);
    }

    public function lockedBranchId(User $user, ?DataScopeLevel $scope = null): ?int
    {
        $scope ??= $this->dataScope->resolve($user);
        if ($scope !== DataScopeLevel::Branch) {
            return null;
        }

        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        return $employee?->branch_id;
    }
}
