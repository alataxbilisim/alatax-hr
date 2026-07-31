<?php

namespace App\Services\Demo;

use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use RuntimeException;

/**
 * Demo verisi sağlık kontrolü — yalnız "kullanıcı var mı" değil;
 * firma + rol + kritik yetki seti (QA-4).
 */
final class DemoSentinel
{
    public const ADMIN_EMAIL = 'admin@demo.test';

    public const COMPANY_SLUG = 'demo-firma';

    /** @var list<string> */
    public const REQUIRED_PERMISSIONS = [
        'employees.list.view',
        'reports.definitions.view',
        'reports.dashboards.view',
        'leaves.requests.view',
        'management.users.view',
    ];

    /**
     * @return array{
     *   ok: bool,
     *   checks: array<string, bool|int|string|null>,
     *   failures: list<string>
     * }
     */
    public function inspect(): array
    {
        $failures = [];
        $checks = [];

        $user = User::query()->where('email', self::ADMIN_EMAIL)->first();
        $checks['user_exists'] = $user !== null;
        if (! $user) {
            $failures[] = self::ADMIN_EMAIL.' yok';

            return ['ok' => false, 'checks' => $checks, 'failures' => $failures];
        }

        $checks['user_active'] = (bool) $user->is_active;
        if (! $user->is_active) {
            $failures[] = 'admin pasif';
        }

        $checks['user_type'] = $user->type instanceof UserType
            ? $user->type->value
            : (string) $user->type;
        if ($user->type !== UserType::CompanyAdmin) {
            $failures[] = 'type company_admin değil ('.$checks['user_type'].')';
        }

        $company = $user->company_id
            ? Company::query()->find($user->company_id)
            : null;
        $checks['company_slug'] = $company?->slug;
        $checks['company_id'] = $company?->id;
        if (! $company || $company->slug !== self::COMPANY_SLUG) {
            $failures[] = 'firma slug '.self::COMPANY_SLUG.' değil';
        }

        $checks['has_admin_role'] = $user->hasRole('admin');
        if (! $checks['has_admin_role']) {
            $failures[] = 'Spatie admin rolü yok';
        }

        $permOk = [];
        foreach (self::REQUIRED_PERMISSIONS as $perm) {
            $can = $user->can($perm);
            $permOk[$perm] = $can;
            if (! $can) {
                $failures[] = "yetki eksik: {$perm}";
            }
        }
        $checks['permissions'] = $permOk;
        $checks['permission_count'] = $user->getAllPermissions()->count();
        if ($checks['permission_count'] < 50) {
            $failures[] = 'admin izin sayısı şüpheli düşük ('.$checks['permission_count'].')';
        }

        $emp = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->first();
        $checks['employee_code'] = $emp?->employee_code;
        if (! $emp || $emp->employee_code !== 'DEM-001') {
            $failures[] = 'admin employee DEM-001 yok';
        }

        $sisterB = Company::query()->where('slug', 'demo-otel-b')->exists();
        $sisterC = Company::query()->where('slug', 'demo-otel-c')->exists();
        $checks['sister_otel_b'] = $sisterB;
        $checks['sister_otel_c'] = $sisterC;
        if (! $sisterB || ! $sisterC) {
            $failures[] = 'kardeş demo firmalar (demo-otel-b/c) eksik';
        }

        return [
            'ok' => $failures === [],
            'checks' => $checks,
            'failures' => $failures,
        ];
    }

    public function assertHealthy(): void
    {
        $result = $this->inspect();
        if (! $result['ok']) {
            throw new RuntimeException(
                'Demo sentinel FAIL: '.implode('; ', $result['failures'])
            );
        }
    }
}
