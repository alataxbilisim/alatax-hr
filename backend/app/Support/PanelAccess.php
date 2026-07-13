<?php

namespace App\Support;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company panel erişimi — izin tabanlı türetim (ayrı bayrak yok).
 *
 * Portal-only: yalnızca self-servis izinler (employee rol seti).
 * Panel: company_admin / super_admin / admin rolü VEYA portal-self dışı herhangi bir izin.
 */
final class PanelAccess
{
    /**
     * Sıradan personelin portal self-servis izinleri (PermissionSeeder employee).
     * Bunların dışında kalan her izin = panel erişimi.
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

        $permissions = $user->getAllPermissions()->pluck('name')->all();

        foreach ($permissions as $name) {
            if (! in_array($name, self::PORTAL_SELF_PERMISSIONS, true)) {
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

        return $query->where(function (Builder $q) use ($portalOnly): void {
            $q->whereIn('type', [
                UserType::CompanyAdmin->value,
                UserType::SuperAdmin->value,
            ])
                ->orWhereHas('roles', function (Builder $rq) use ($portalOnly): void {
                    $rq->where('name', 'admin')
                        ->orWhereHas('permissions', function (Builder $pq) use ($portalOnly): void {
                            $pq->whereNotIn('name', $portalOnly);
                        });
                })
                ->orWhereHas('permissions', function (Builder $pq) use ($portalOnly): void {
                    $pq->whereNotIn('name', $portalOnly);
                });
        });
    }
}
