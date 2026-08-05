<?php

namespace App\Support;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company panel erişimi — roles.panel_access + type (Tur8).
 *
 * Portal-only: panel_access=false roller (varsayılan: employee) ve portal-self dışı
 * doğrudan izin yok.
 * Panel: company_admin / super_admin VEYA en az bir panel_access=true rol
 *         VEYA portal-self dışı doğrudan atanmış izin.
 */
final class PanelAccess
{
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

        if ($user->roles()->where('panel_access', true)->exists()) {
            return true;
        }

        foreach ($user->getDirectPermissions()->pluck('name') as $name) {
            if (! in_array((string) $name, self::PORTAL_SELF_PERMISSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     * @return Builder<\App\Models\User>
     */
    public static function constrainUsersQuery(Builder $query): Builder
    {
        $portalOnly = self::PORTAL_SELF_PERMISSIONS;

        return $query->where(function (Builder $q) use ($portalOnly): void {
            $q->whereIn('type', [
                UserType::CompanyAdmin->value,
                UserType::SuperAdmin->value,
            ])
                ->orWhereHas('roles', function (Builder $rq): void {
                    $rq->where('panel_access', true);
                })
                ->orWhereHas('permissions', function (Builder $pq) use ($portalOnly): void {
                    $pq->whereNotIn('name', $portalOnly);
                });
        });
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     * @return Builder<\App\Models\User>
     */
    public static function constrainPortalOnlyQuery(Builder $query): Builder
    {
        $portalOnly = self::PORTAL_SELF_PERMISSIONS;

        return $query
            ->whereHas('employee')
            ->whereNotIn('type', [
                UserType::CompanyAdmin->value,
                UserType::SuperAdmin->value,
            ])
            ->whereDoesntHave('roles', function (Builder $rq): void {
                $rq->where('panel_access', true);
            })
            ->whereDoesntHave('permissions', function (Builder $pq) use ($portalOnly): void {
                $pq->whereNotIn('name', $portalOnly);
            });
    }

    /**
     * @var list<string>
     */
    public const GRANTABLE_PANEL_ROLES = [
        'hr_manager',
        'hr_specialist',
        'manager',
        'admin',
    ];
}
