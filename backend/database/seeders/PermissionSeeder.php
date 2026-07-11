<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Hiyerarşik Yetki Sistemi
     * Format: {module}.{page}.{action}
     *
     * Wildcard desteği:
     * - module.* = Modüldeki tüm yetkiler
     * - module.page.* = Sayfadaki tüm yetkiler
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Hiyerarşik yetki tanımları
        $hierarchicalPermissions = $this->getHierarchicalPermissions();

        // Geriye uyumluluk için eski yetkiler
        $legacyPermissions = $this->getLegacyPermissions();

        // Tüm yetkileri birleştir
        $allPermissions = array_merge($hierarchicalPermissions, $legacyPermissions);

        // Tüm permission'ları oluştur
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum']
            );
        }

        // Varsayılan rolleri oluştur
        $this->createRoles($allPermissions);
    }

    /**
     * Hiyerarşik yetki tanımları
     */
    private function getHierarchicalPermissions(): array
    {
        $permissions = [];

        // Sayfa bazlı aksiyon tanımları
        $modulePages = [
            // Yönetim Modülü
            'management' => [
                'users' => ['view', 'create', 'edit', 'delete', 'export', 'import'],
                'roles' => ['view', 'create', 'edit', 'delete'],
                'branches' => ['view', 'create', 'edit', 'delete'],
                'audit_logs' => ['view', 'export'],
                'settings' => ['view', 'edit'],
                'webhooks' => ['view', 'create', 'edit', 'delete'],
                'api_keys' => ['view', 'create', 'edit', 'delete'],
            ],

            // Personel Modülü
            'employees' => [
                'list' => ['view', 'create', 'edit', 'delete', 'export', 'import'],
                'departments' => ['view', 'create', 'edit', 'delete'],
                'organization' => ['view'],
                'custom_fields' => ['view', 'create', 'edit', 'delete'],
                'reports' => ['view', 'export'],
                'documents' => ['view', 'create', 'edit', 'delete'],
            ],

            // İşe Alım Modülü
            'recruitment' => [
                'positions' => ['view', 'create', 'edit', 'delete'],
                'applications' => ['view', 'edit', 'delete', 'approve'],
                'cv_pool' => ['view', 'edit', 'export'],
                'interviews' => ['view', 'create', 'edit', 'delete'],
                'reports' => ['view', 'export'],
                'forms' => ['view', 'create', 'edit', 'delete'],
                'custom_fields' => ['view', 'create', 'edit', 'delete'],
            ],

            // İzin Yönetimi Modülü
            'leaves' => [
                'requests' => ['view', 'create', 'edit', 'delete', 'approve'],
                'types' => ['view', 'create', 'edit', 'delete'],
                'balances' => ['view', 'edit'],
                'calendar' => ['view'],
                'holidays' => ['view', 'create', 'edit', 'delete'],
                'accrual_policies' => ['view', 'create', 'edit', 'delete'],
                'custom_fields' => ['view', 'create', 'edit', 'delete'],
            ],

            // Evrak Yönetimi Modülü
            'documents' => [
                'list' => ['view', 'create', 'edit', 'delete', 'approve'],
                'categories' => ['view', 'create', 'edit', 'delete'],
                'reports' => ['view', 'export'],
                'custom_fields' => ['view', 'create', 'edit', 'delete'],
            ],

            // Onboarding Modülü
            'onboarding' => [
                'processes' => ['view', 'create', 'edit', 'delete'],
                'templates' => ['view', 'create', 'edit', 'delete'],
            ],

            // Performans Modülü
            'performance' => [
                'reviews' => ['view', 'create', 'edit', 'delete', 'approve'],
                'periods' => ['view', 'create', 'edit', 'delete'],
                'criteria' => ['view', 'create', 'edit', 'delete'],
                'okr' => ['view', 'create', 'edit', 'delete'],
                'feedback' => ['view', 'create'],
                'competencies' => ['view', 'create', 'edit', 'delete'],
                'one_on_one' => ['view', 'create', 'edit', 'delete'],
                'custom_fields' => ['view', 'create', 'edit', 'delete'],
            ],

            // Eğitim Modülü
            'training' => [
                'list' => ['view', 'create', 'edit', 'delete'],
                'sessions' => ['view', 'create', 'edit', 'delete'],
                'custom_fields' => ['view', 'create', 'edit', 'delete'],
            ],

            // Varlık Yönetimi Modülü
            'assets' => [
                'list' => ['view', 'create', 'edit', 'delete', 'export'],
                'categories' => ['view', 'create', 'edit', 'delete'],
                'assignments' => ['view', 'create', 'edit', 'delete'],
                'maintenance' => ['view', 'create', 'edit', 'delete'],
                'custom_fields' => ['view', 'create', 'edit', 'delete'],
            ],

            // Anketler Modülü
            'surveys' => [
                'list' => ['view', 'create', 'edit', 'delete'],
            ],

            // Analitik Modülü
            'analytics' => [
                'reports' => ['view', 'export'],
            ],

            // Puantaj Modülü
            'timesheet' => [
                'attendance' => ['view', 'create', 'edit', 'approve'],
                'shifts' => ['view', 'create', 'edit', 'delete'],
            ],

            // Masraf Yönetimi Modülü
            'expenses' => [
                'claims' => ['view', 'create', 'edit', 'delete', 'approve'],
                'categories' => ['view', 'create', 'edit', 'delete'],
            ],
        ];

        // Yetkileri oluştur
        foreach ($modulePages as $module => $pages) {
            // Modül wildcard yetkisi
            $permissions[] = "{$module}.*";

            foreach ($pages as $page => $actions) {
                // Sayfa wildcard yetkisi
                $permissions[] = "{$module}.{$page}.*";

                // Sayfa aksiyonları
                foreach ($actions as $action) {
                    $permissions[] = "{$module}.{$page}.{$action}";
                }
            }
        }

        return $permissions;
    }

    /**
     * Geriye uyumluluk için eski yetki formatları
     */
    private function getLegacyPermissions(): array
    {
        return [
            // Kullanıcı yönetimi
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Rol yönetimi
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // Personel yönetimi
            'employees.view',
            'employees.create',
            'employees.edit',
            'employees.delete',

            // Şube yönetimi
            'branches.view',
            'branches.create',
            'branches.edit',
            'branches.delete',

            // Firma ayarları
            'company.view',
            'company.edit',
            'settings.view',
            'settings.edit',

            // İş başvuru modülü
            'job-positions.view',
            'job-positions.create',
            'job-positions.edit',
            'job-positions.delete',
            'applications.view',
            'applications.create',
            'applications.edit',
            'applications.delete',
            'applications.change-status',
            'cv-pool.view',
            'cv-pool.export',
            'recruitment.view',
            'recruitment.create',
            'recruitment.edit',
            'recruitment.delete',

            // Evrak yönetimi
            'documents.view',
            'documents.create',
            'documents.edit',
            'documents.delete',
            'documents.approve',

            // Onboarding
            'onboarding.view',
            'onboarding.create',
            'onboarding.edit',
            'onboarding.delete',

            // İzin yönetimi
            'leaves.view',
            'leaves.create',
            'leaves.edit',
            'leaves.delete',
            'leaves.approve',
            'leave-types.manage',

            // Eğitim yönetimi
            'trainings.view',
            'trainings.create',
            'trainings.edit',
            'trainings.delete',

            // Performans yönetimi
            'performance.view',
            'performance.create',
            'performance.edit',
            'performance.delete',

            // Varlık yönetimi
            'assets.view',
            'assets.create',
            'assets.edit',
            'assets.delete',

            // Raporlar
            'reports.view',
            'reports.export',

            // Log görüntüleme ve denetim
            'logs.view',
            'audit.view',
            'audit.export',
        ];
    }

    /**
     * Varsayılan rolleri oluştur
     */
    private function createRoles(array $allPermissions): void
    {
        $roles = [
            'admin' => [
                'description' => 'Firma Yöneticisi - Tüm yetkiler',
                'permissions' => $allPermissions, // Tüm yetkiler
            ],
            'hr_manager' => [
                'description' => 'İK Müdürü',
                'permissions' => [
                    // Yönetim
                    'management.users.view', 'management.users.create', 'management.users.edit',
                    'management.roles.view',
                    'management.branches.view', 'management.branches.create', 'management.branches.edit',
                    'management.settings.view',
                    'management.audit_logs.view',

                    // Personel - Tam yetki
                    'employees.*',

                    // İşe Alım - Tam yetki
                    'recruitment.*',

                    // Evrak - Tam yetki
                    'documents.*',

                    // İzin - Tam yetki
                    'leaves.*',

                    // Onboarding - Tam yetki
                    'onboarding.*',

                    // Eğitim - Görüntüleme ve düzenleme
                    'training.list.view', 'training.list.create', 'training.list.edit',
                    'training.sessions.view', 'training.sessions.create', 'training.sessions.edit',

                    // Performans - Görüntüleme ve düzenleme
                    'performance.reviews.view', 'performance.reviews.create', 'performance.reviews.edit',
                    'performance.periods.view',
                    'performance.criteria.view',

                    // Varlıklar - Görüntüleme ve düzenleme
                    'assets.list.view', 'assets.list.create', 'assets.list.edit',
                    'assets.assignments.view', 'assets.assignments.create', 'assets.assignments.edit',

                    // Anketler
                    'surveys.list.view', 'surveys.list.create', 'surveys.list.edit',

                    // Analitik
                    'analytics.reports.view', 'analytics.reports.export',

                    // Geriye uyumluluk
                    'users.view', 'users.create', 'users.edit',
                    'roles.view',
                    'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
                    'branches.view', 'branches.create', 'branches.edit',
                    'company.view', 'settings.view',
                    'reports.view', 'reports.export',
                    'logs.view', 'audit.view',
                ],
            ],
            'hr_specialist' => [
                'description' => 'İK Uzmanı',
                'permissions' => [
                    // Yönetim - Sadece görüntüleme
                    'management.users.view',
                    'management.branches.view',

                    // Personel - Görüntüleme ve düzenleme
                    'employees.list.view', 'employees.list.create', 'employees.list.edit',
                    'employees.departments.view',
                    'employees.organization.view',
                    'employees.documents.view', 'employees.documents.create', 'employees.documents.edit',

                    // İşe Alım
                    'recruitment.positions.view',
                    'recruitment.applications.view', 'recruitment.applications.edit',
                    'recruitment.cv_pool.view', 'recruitment.cv_pool.edit',
                    'recruitment.interviews.view', 'recruitment.interviews.create', 'recruitment.interviews.edit',
                    'recruitment.reports.view',
                    'recruitment.forms.view',

                    // Evrak
                    'documents.list.view', 'documents.list.create', 'documents.list.edit',
                    'documents.categories.view',
                    'documents.reports.view',

                    // İzin
                    'leaves.requests.view', 'leaves.requests.create', 'leaves.requests.edit',
                    'leaves.types.view',
                    'leaves.balances.view',
                    'leaves.calendar.view',
                    'leaves.holidays.view',
                    'leaves.accrual_policies.view',

                    // Onboarding
                    'onboarding.processes.view', 'onboarding.processes.edit',
                    'onboarding.templates.view',

                    // Eğitim
                    'training.list.view', 'training.list.create', 'training.list.edit',
                    'training.sessions.view', 'training.sessions.create', 'training.sessions.edit',

                    // Performans - Sadece görüntüleme
                    'performance.reviews.view',

                    // Geriye uyumluluk
                    'users.view',
                    'employees.view', 'employees.create', 'employees.edit',
                    'branches.view',
                    'reports.view',
                ],
            ],
            'manager' => [
                'description' => 'Departman Yöneticisi',
                'permissions' => [
                    // Personel - Sadece görüntüleme
                    'employees.list.view',
                    'employees.departments.view',
                    'employees.organization.view',

                    // İşe Alım - Görüntüleme
                    'recruitment.applications.view',

                    // Evrak - Görüntüleme
                    'documents.list.view',

                    // İzin - Görüntüleme ve onay
                    'leaves.requests.view', 'leaves.requests.approve',
                    'leaves.calendar.view',

                    // Performans - Değerlendirme yapabilir
                    'performance.reviews.view', 'performance.reviews.create', 'performance.reviews.edit',
                    'performance.feedback.view', 'performance.feedback.create',
                    'performance.one_on_one.view', 'performance.one_on_one.create', 'performance.one_on_one.edit',

                    // Geriye uyumluluk
                    'users.view',
                    'employees.view',
                    'applications.view',
                    'documents.view',
                    'leaves.view', 'leaves.approve',
                    'performance.view', 'performance.create', 'performance.edit',
                    'reports.view',
                ],
            ],
            'employee' => [
                'description' => 'Çalışan',
                'permissions' => [
                    // Personel - Sadece kendi profilini
                    'employees.list.view',

                    // Evrak - Görüntüleme
                    'documents.list.view',

                    // İzin - Kendi taleplerini
                    'leaves.requests.view', 'leaves.requests.create',
                    'leaves.calendar.view',

                    // Eğitim - Görüntüleme
                    'training.list.view',
                    'training.sessions.view',

                    // Performans - Kendi değerlendirmelerini
                    'performance.reviews.view',
                    'performance.feedback.view',

                    // Geriye uyumluluk
                    'employees.view',
                    'documents.view',
                    'leaves.view', 'leaves.create',
                    'trainings.view',
                ],
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'sanctum']
            );

            // Sadece mevcut yetkileri ata (olmayan yetkileri atla)
            $validPermissions = array_filter($roleData['permissions'], function ($perm) {
                return Permission::where('name', $perm)->where('guard_name', 'sanctum')->exists();
            });

            $role->syncPermissions($validPermissions);
        }
    }
}
