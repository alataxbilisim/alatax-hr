<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Operasyonel şirket bağlamı — membership doğrulamalı (G1).
 * Desen: BranchContextService (A6) kabuğu; tenant sınırı burada.
 */
class CompanyContextService
{
    public const HEADER = 'X-Company-Id';

    /**
     * @return array{
     *   companies: list<array{id: int, name: string, slug: string, is_active: bool}>,
     *   active_company_id: int|null
     * }
     */
    public function availableFor(User $user): array
    {
        if ($user->type === UserType::SuperAdmin) {
            return [
                'companies' => [],
                'active_company_id' => null,
            ];
        }

        $memberships = CompanyUser::query()
            ->where('user_id', $user->id)
            ->with(['company:id,name,slug,status'])
            ->orderByDesc('is_default')
            ->orderBy('company_id')
            ->get();

        $companies = $memberships
            ->filter(fn (CompanyUser $m) => $m->company !== null)
            ->map(fn (CompanyUser $m) => [
                'id' => (int) $m->company->id,
                'name' => $m->company->name,
                'slug' => $m->company->slug,
                'is_active' => $m->company->isActive(),
            ])
            ->values()
            ->all();

        $activeId = CompanyContext::id();

        return [
            'companies' => $companies,
            'active_company_id' => $activeId,
        ];
    }

    /**
     * Kullanıcının erişebildiği şirket id listesi.
     *
     * @return list<int>
     */
    public function accessibleCompanyIds(User $user): array
    {
        return CompanyUser::query()
            ->where('user_id', $user->id)
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function hasMembership(User $user, int $companyId): bool
    {
        return CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->exists();
    }

    /**
     * Header / fallback sırası ile bağlam çöz; membership yoksa 403.
     */
    public function resolveFromRequest(Request $request, User $user): CompanyContext
    {
        if ($user->type === UserType::SuperAdmin) {
            throw new HttpException(403, 'SuperAdmin operasyonel şirket bağlamı kullanmaz.');
        }

        // Portal: şirket seçici yok — header yok sayılır; panel last_company_id sızmaz
        $isPortal = $request->is('api/v1/portal') || $request->is('api/v1/portal/*');

        $raw = $isPortal ? null : $request->header(self::HEADER);
        $raw = is_string($raw) || is_numeric($raw) ? trim((string) $raw) : null;

        if ($raw !== null && $raw !== '') {
            $companyId = (int) $raw;
            if ($companyId < 1) {
                ActivityLog::log(
                    'company_context_denied',
                    $user,
                    "Geçersiz şirket bağlamı: {$raw}",
                    null,
                    ['requested' => $raw],
                    false,
                    'company_context_invalid'
                );
                throw new HttpException(403, 'Geçersiz şirket seçimi.');
            }

            if (! $this->hasMembership($user, $companyId)) {
                ActivityLog::log(
                    'company_context_denied',
                    $user,
                    "Şirket bağlamı reddedildi (membership yok): {$companyId}",
                    null,
                    ['requested' => $companyId],
                    false,
                    'company_context_forbidden'
                );
                throw new HttpException(403, 'Bu şirket için yetkiniz yok.');
            }

            $this->rememberLastCompany($user, $companyId);

            return CompanyContext::bind($companyId);
        }

        // Portal: home company (personel işvereni). Panel: last_company → default → home.
        $candidate = $isPortal
            ? $this->resolvePortalCompanyId($user)
            : $this->resolveFallbackCompanyId($user);
        if ($candidate === null) {
            throw new HttpException(403, 'Erişilebilir şirket bulunamadı.');
        }

        if (! $this->hasMembership($user, $candidate)) {
            $this->ensureMembership($user, $candidate, true);
        }

        // Portal last_company'yi panelle karıştırmaz
        if (! $isPortal) {
            $this->rememberLastCompany($user, $candidate);
        }

        return CompanyContext::bind($candidate);
    }

    /**
     * Portal operasyonel şirket: home / personel kaydı — last_company_id yok sayılır.
     */
    public function resolvePortalCompanyId(User $user): ?int
    {
        if ($user->company_id !== null && $this->hasMembership($user, (int) $user->company_id)) {
            return (int) $user->company_id;
        }

        $empCompanyId = \App\Models\Employee::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->value('company_id');
        if ($empCompanyId !== null && $this->hasMembership($user, (int) $empCompanyId)) {
            return (int) $empCompanyId;
        }

        return $this->resolveFallbackCompanyId($user, ignoreLastCompany: true);
    }

    public function resolveFallbackCompanyId(User $user, bool $ignoreLastCompany = false): ?int
    {
        if (! $ignoreLastCompany
            && $user->last_company_id !== null
            && $this->hasMembership($user, (int) $user->last_company_id)) {
            return (int) $user->last_company_id;
        }

        $default = CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->value('company_id');

        if ($default !== null) {
            return (int) $default;
        }

        $any = CompanyUser::query()
            ->where('user_id', $user->id)
            ->orderBy('company_id')
            ->value('company_id');

        if ($any !== null) {
            return (int) $any;
        }

        return $user->company_id !== null ? (int) $user->company_id : null;
    }

    public function rememberLastCompany(User $user, int $companyId): void
    {
        if ((int) $user->last_company_id === $companyId) {
            return;
        }

        $user->forceFill(['last_company_id' => $companyId])->saveQuietly();
    }

    /**
     * Membership + isteğe bağlı default.
     */
    public function ensureMembership(User $user, int $companyId, bool $isDefault = false): CompanyUser
    {
        $row = CompanyUser::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'company_id' => $companyId,
            ],
            [
                'role_id' => null,
                'is_default' => $isDefault,
                'created_at' => now(),
            ]
        );

        if ($isDefault && ! $row->is_default) {
            DB::transaction(function () use ($user, $row): void {
                CompanyUser::query()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $row->id)
                    ->update(['is_default' => false]);
                $row->forceFill(['is_default' => true])->save();
            });
        }

        return $row->fresh() ?? $row;
    }

    /**
     * Yeni kullanıcı / firma atamasında home membership.
     */
    public function syncHomeMembership(User $user): void
    {
        if ($user->type === UserType::SuperAdmin || $user->company_id === null) {
            return;
        }

        $companyId = (int) $user->company_id;
        $this->ensureMembership($user, $companyId, true);

        if ($user->last_company_id === null) {
            $user->forceFill(['last_company_id' => $companyId])->saveQuietly();
        }
    }

    /**
     * Firma için organization yoksa 1:1 oluştur.
     */
    public function ensureOrganizationForCompany(Company $company): void
    {
        if ($company->organization_id !== null) {
            return;
        }

        $org = \App\Models\Organization::query()->create([
            'name' => $company->name,
            'slug' => 'org-'.($company->slug ?: 'company-'.$company->id),
        ]);

        $company->forceFill(['organization_id' => $org->id])->saveQuietly();
    }
}
