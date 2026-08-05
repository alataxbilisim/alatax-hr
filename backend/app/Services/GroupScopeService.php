<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyContext;

/**
 * G2 — Rapor/pano grup kapsamı: organization ∩ membership.
 * Operasyonel CRUD bu servisi kullanmaz.
 */
class GroupScopeService
{
    public function __construct(
        protected CompanyContextService $companyContext,
    ) {}

    /**
     * Aktif şirketin organization'ındaki şirketler ∩ kullanıcının membership'leri.
     *
     * @return list<int>
     */
    public function reportableCompanyIds(User $user, ?int $activeCompanyId = null): array
    {
        $activeId = $activeCompanyId
            ?? CompanyContext::id()
            ?? ($user->home_company_id ? (int) $user->home_company_id : null);

        if ($activeId === null || $activeId < 1) {
            return [];
        }

        $active = Company::query()->find($activeId);
        if ($active === null) {
            return [];
        }

        $membershipIds = $this->companyContext->accessibleCompanyIds($user);
        if ($membershipIds === []) {
            return [];
        }

        if ($active->organization_id === null) {
            return in_array($activeId, $membershipIds, true) ? [$activeId] : [];
        }

        $orgIds = Company::query()
            ->where('organization_id', $active->organization_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids = array_values(array_intersect($orgIds, $membershipIds));
        sort($ids);

        return $ids;
    }

    public function assertCanUseGroupScope(User $user): void
    {
        if (! $user->can('reports.scope.group')) {
            abort(403, 'Grup kapsamı için yetkiniz yok (reports.scope.group).');
        }
    }

    /**
     * @return list<int>
     */
    public function resolveForReport(User $user, int $activeCompanyId, string $scope): array
    {
        if ($scope !== 'group') {
            return [$activeCompanyId];
        }

        $this->assertCanUseGroupScope($user);

        $ids = $this->reportableCompanyIds($user, $activeCompanyId);
        if ($ids === []) {
            return [$activeCompanyId];
        }

        return $ids;
    }
}
