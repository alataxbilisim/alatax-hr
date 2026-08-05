<?php

namespace App\Support;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company panel erişimi — izin + rol tabanlı türetim (ayrı bayrak yok).
 *
 * Portal-only: yalnızca Spatie `employee` rolü (self-servis izin seti).
 * Panel: company_admin / super_admin / admin rolü VEYA `employee` dışı herhangi bir rol
 *         VEYA portal-self dışı doğrudan atanmış izin.
 *
 * Tur7: dar yetkili custom rol (ör. yalnız employees.list.view) panel sayılır —
 * aksi halde /users listesinden kaybolur ve panele giremez.
 */
final class PanelAccess
{
    /** Portal self-servis rol adı (PermissionSeeder). */
    public const PORTAL_ROLE = 'employee';

    /**
     * Sıradan personelin portal self-servis izinleri (PermissionSeeder employee).
     * Doğrudan (rol dışı) atamada hâlâ portal-only sınırı için kullanılır.
     *
     * @var list<string>
     */
    public const PORTAL_SELF_PERMISSIONS = [
        'employees.list.view',
        'employees.view',
        'documents.list.view',
        'documents.view',
        'leaves.requests.view',
        'leaves.requests.create',
        'leaves.calendar.view',
        'leaves.view',
        'leaves.create',
        'training.list.view',
        'training.sessions.view',
        'trainings.view',
        'performance.reviews.view',
        'performance.feedback.view',
    ];

    public static function has(User $user): bool
    {
        if ($user->type === UserType::SuperAdmin || $user->type === UserType::CompanyAdmin) {
            return true;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        foreach ($user->getRoleNames() as $roleName) {
            if ((string) $roleName !== self::PORTAL_ROLE) {
                return true;
            }
        }

        // Rol yok / yalnız employee — doğrudan atanmış panel izni
        foreach ($user->getDirectPermissions()->pluck('name') as $name) {
            if (! in_array((string) $name, self::PORTAL_SELF_PERMISSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Panel erişimli kullanıcıları filtrele (pagination uyumlu).
     *
     * @param  Builder<\App\Models\User>  $query
     * @return Builder<\App\Models\User>
     */
    public static function constrainUsersQuery(Builder $query): Builder
    {
        $portalOnly = self::PORTAL_SELF_PERMISSIONS;
        $portalRole = self::PORTAL_ROLE;

        return $query->where(function (Builder $q) use ($portalOnly, $portalRole): void {
            $q->whereIn('type', [
                UserType::CompanyAdmin->value,
                UserType::SuperAdmin->value,
            ])
                ->orWhereHas('roles', function (Builder $rq) use ($portalRole): void {
                    $rq->where('name', '!=', $portalRole);
                })
                ->orWhereHas('permissions', function (Builder $pq) use ($portalOnly): void {
                    $pq->whereNotIn('name', $portalOnly);
                });
        });
    }

    /**
     * Portal erişimi olan ama panel erişimi olmayan kullanıcılar
     * (personel kaydı + PanelAccess::has === false).
     *
     * @param  Builder<\App\Models\User>  $query
     * @return Builder<\App\Models\User>
     */
    public static function constrainPortalOnlyQuery(Builder $query): Builder
    {
        $portalOnly = self::PORTAL_SELF_PERMISSIONS;
        $portalRole = self::PORTAL_ROLE;

        return $query
            ->whereHas('employee')
            ->whereNotIn('type', [
                UserType::CompanyAdmin->value,
                UserType::SuperAdmin->value,
            ])
            ->whereDoesntHave('roles', function (Builder $rq) use ($portalRole): void {
                $rq->where('name', '!=', $portalRole);
            })
            ->whereDoesntHave('permissions', function (Builder $pq) use ($portalOnly): void {
                $pq->whereNotIn('name', $portalOnly);
            });
    }

    /**
     * Personelden panele yükseltmede atanabilir varsayılan roller.
     *
     * @var list<string>
     */
    public const GRANTABLE_PANEL_ROLES = [
        'hr_manager',
        'hr_specialist',
        'manager',
        'admin',
    ];
}
