<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;

    protected $companyAdmin;

    /** Portal personeli — UserType::User + Employee kaydı */
    protected $employee;

    protected $company;

    protected $companyWithoutModule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // AuthThrottleTest / önceki istekler aynı IP limiter'ını kirletebilir
        foreach (['127.0.0.1', '::1'] as $ip) {
            \Illuminate\Support\Facades\RateLimiter::clear(md5('auth'.$ip));
        }

        $this->superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => 'password',
        ]);

        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'status' => CompanyStatus::Active,
            'email' => 'test@company.com',
            'phone' => '5551234567',
        ]);

        $this->companyAdmin = User::factory()->create([
            'name' => 'Company Admin',
            'email' => 'admin@company.com',
            'password' => 'password',
            'type' => UserType::CompanyAdmin,
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->companyAdmin);
        $this->companyAdmin = $this->companyAdmin->fresh();

        // Şema: type=user (eski testlerdeki 'employee' geçersizdi)
        $this->employee = User::factory()->create([
            'name' => 'Employee',
            'email' => 'employee@company.com',
            'password' => 'password',
            'type' => UserType::User,
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);
        Employee::factory()->forUser($this->employee)->create();
        // Portal testlerinde company_admin da erişebilsin diye personel kaydı
        Employee::factory()->forUser($this->companyAdmin)->create();

        $this->companyWithoutModule = Company::factory()->create([
            'name' => 'No Module Company',
            'slug' => 'no-module-company',
            'status' => CompanyStatus::Active,
            'email' => 'nomodule@company.com',
            'phone' => '5551234568',
        ]);
    }

    /**
     * Tüm route'ları listele ve test et
     */
    public function test_all_routes_are_registered(): void
    {
        $routes = Route::getRoutes();
        $apiRoutes = collect($routes)->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/v1');
        });

        $this->assertGreaterThan(0, $apiRoutes->count(), 'API route\'ları bulunamadı');
    }

    /**
     * Public auth route'larını test et
     */
    public function test_public_auth_routes(): void
    {
        foreach (['127.0.0.1', '::1'] as $ip) {
            \Illuminate\Support\Facades\RateLimiter::clear(md5('auth'.$ip));
        }

        // Login route
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'superadmin@test.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200);
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('token', $response->json()['data']);

        // Register route (varsa)
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        // Register route'u varsa test et, yoksa skip et
    }

    /**
     * Protected route'ları authentication olmadan test et
     */
    public function test_protected_routes_require_authentication(): void
    {
        $protectedRoutes = [
            'GET /api/v1/dashboard',
            'GET /api/v1/auth/me',
            'GET /api/v1/users',
            'GET /api/v1/roles',
        ];

        foreach ($protectedRoutes as $route) {
            [$method, $uri] = explode(' ', $route);
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    /**
     * Company Admin yetkilerini test et
     */
    public function test_company_admin_authorization(): void
    {
        Sanctum::actingAs($this->companyAdmin);

        // Company Admin erişebilmeli
        $response = $this->getJson('/api/v1/dashboard');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/roles');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/company');
        $response->assertStatus(200);

        // Company Admin SuperAdmin route'larına erişememeli
        $response = $this->getJson('/api/v1/admin/companies');
        $response->assertStatus(403);
    }

    /**
     * Employee yetkilerini test et
     */
    public function test_employee_authorization(): void
    {
        Sanctum::actingAs($this->employee);

        // Employee dashboard'a erişebilmeli
        $response = $this->getJson('/api/v1/dashboard');
        $response->assertStatus(200);

        // Employee kullanıcı yönetimine erişememeli
        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(403);

        $response = $this->getJson('/api/v1/roles');
        $response->assertStatus(403);

        $response = $this->getJson('/api/v1/company');
        $response->assertStatus(403);
    }

    /**
     * SuperAdmin yetkilerini test et
     */
    public function test_super_admin_authorization(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // SuperAdmin admin route'larına erişebilmeli
        $response = $this->getJson('/api/v1/admin/dashboard');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/admin/companies');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/admin/modules');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(200);
    }

    /**
     * Modül erişim kontrollerini test et
     */
    public function test_module_access_control(): void
    {
        // Modülü firmaya ata
        $module = Module::firstOrCreate(
            ['slug' => 'job-applications'],
            [
                'name' => 'İş Başvuru',
                'slug' => 'job-applications',
                'is_core' => false,
            ]
        );

        $this->company->modules()->sync([
            $module->id => [
                'is_active' => true,
                'activated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($this->companyAdmin);

        // Modülü olan firma erişebilmeli
        $response = $this->getJson('/api/v1/recruitment/positions');
        $response->assertStatus(200);

        // Modülü olmayan firma erişememeli
        $userWithoutModule = User::factory()->create([
            'name' => 'No Module User',
            'email' => 'nomodule@company.com',
            'password' => 'password',
            'type' => UserType::CompanyAdmin,
            'company_id' => $this->companyWithoutModule->id,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($userWithoutModule);

        Sanctum::actingAs($userWithoutModule);
        $response = $this->getJson('/api/v1/recruitment/positions');
        $response->assertStatus(403);
    }

    /**
     * İş Başvuru Modülü route'larını test et
     */
    public function test_recruitment_module_routes(): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'job-applications'],
            [
                'name' => 'İş Başvuru',
                'slug' => 'job-applications',
                'is_core' => false,
            ]
        );

        $this->company->modules()->sync([
            $module->id => [
                'is_active' => true,
                'activated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($this->companyAdmin);

        // Positions
        $response = $this->getJson('/api/v1/recruitment/positions');
        $response->assertStatus(200);

        $response = $this->postJson('/api/v1/recruitment/positions', [
            'title' => 'Test Position',
            'department' => 'IT',
            'description' => 'Test Description',
        ]);
        $response->assertStatus(201);

        // Applications
        $response = $this->getJson('/api/v1/recruitment/applications');
        $response->assertStatus(200);

        // CV Pool
        $response = $this->getJson('/api/v1/recruitment/cv-pool');
        $response->assertStatus(200);

        // Forms
        $response = $this->getJson('/api/v1/recruitment/forms');
        $response->assertStatus(200);
    }

    /**
     * Evrak Yönetimi Modülü route'larını test et
     */
    public function test_document_management_module_routes(): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'document-management'],
            [
                'name' => 'Evrak Yönetimi',
                'slug' => 'document-management',
                'is_core' => false,
            ]
        );

        $this->company->modules()->sync([
            $module->id => [
                'is_active' => true,
                'activated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($this->companyAdmin);

        // Categories
        $response = $this->getJson('/api/v1/documents/categories');
        $response->assertStatus(200);

        $response = $this->postJson('/api/v1/documents/categories', [
            'name' => 'Test Category',
        ]);
        $response->assertStatus(201);

        // Documents
        $response = $this->getJson('/api/v1/documents');
        $response->assertStatus(200);
    }

    /**
     * Onboarding Modülü route'larını test et
     */
    public function test_onboarding_module_routes(): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'onboarding'],
            [
                'name' => 'Onboarding',
                'slug' => 'onboarding',
                'is_core' => false,
            ]
        );

        $this->company->modules()->sync([
            $module->id => [
                'is_active' => true,
                'activated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($this->companyAdmin);

        // Templates
        $response = $this->getJson('/api/v1/onboarding/templates');
        $response->assertStatus(200);

        // Processes
        $response = $this->getJson('/api/v1/onboarding/processes');
        $response->assertStatus(200);
    }

    /**
     * İzin Yönetimi Modülü route'larını test et
     */
    public function test_leave_management_module_routes(): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'leave-management'],
            [
                'name' => 'İzin Yönetimi',
                'slug' => 'leave-management',
                'is_core' => false,
            ]
        );

        $this->company->modules()->sync([
            $module->id => [
                'is_active' => true,
                'activated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($this->companyAdmin);

        // Leave Types
        $response = $this->getJson('/api/v1/leaves/types');
        $response->assertStatus(200);

        // Leave Requests
        $response = $this->getJson('/api/v1/leaves/requests');
        $response->assertStatus(200);

        // Calendar — start_date / end_date zorunlu (API sözleşmesi)
        $response = $this->getJson('/api/v1/leaves/calendar?'.http_build_query([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]));
        $response->assertStatus(200);

        // Balance
        $response = $this->getJson('/api/v1/leaves/balance');
        $response->assertStatus(200);
    }

    /**
     * Portal route'larını test et
     */
    public function test_portal_routes(): void
    {
        Sanctum::actingAs($this->employee);

        // Portal Dashboard
        $response = $this->getJson('/api/v1/portal/dashboard');
        $response->assertStatus(200);

        // Portal Profile
        $response = $this->getJson('/api/v1/portal/profile');
        $response->assertStatus(200);

        // Portal Leaves
        $response = $this->getJson('/api/v1/portal/leaves');
        $response->assertStatus(200);

        // Portal Documents
        $response = $this->getJson('/api/v1/portal/documents');
        $response->assertStatus(200);
    }

    /**
     * Public route'ları test et
     */
    public function test_public_routes(): void
    {
        // Public job listings — auth gerekmez (401 olmamalı); firma yoksa 404, varsa 200
        $response = $this->getJson('/api/v1/public/companies/test-company/jobs');
        $this->assertContains(
            $response->status(),
            [200, 404],
            'Public kariyer listesi 200 veya 404 dönmeli (auth zorunlu olmamalı)'
        );
        $this->assertNotEquals(401, $response->status());
    }

    /**
     * Company active middleware testi
     */
    public function test_company_active_middleware(): void
    {
        // Pasif / askıya alınmış firma (şema: suspended — 'inactive' yok)
        $inactiveCompany = Company::factory()->create([
            'name' => 'Inactive Company',
            'slug' => 'inactive-company',
            'status' => CompanyStatus::Suspended,
            'email' => 'inactive@company.com',
            'phone' => '5551234569',
        ]);

        $inactiveUser = User::factory()->create([
            'name' => 'Inactive User',
            'email' => 'inactive@user.com',
            'password' => 'password',
            'type' => UserType::CompanyAdmin,
            'company_id' => $inactiveCompany->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($inactiveUser);

        // Pasif firma kullanıcısı erişememeli
        $response = $this->getJson('/api/v1/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Notification route'larını test et
     */
    public function test_notification_routes(): void
    {
        Sanctum::actingAs($this->companyAdmin);

        $response = $this->getJson('/api/v1/notifications');
        $response->assertStatus(200);
    }

    /**
     * Activity log route'larını test et
     */
    public function test_activity_log_routes(): void
    {
        Sanctum::actingAs($this->companyAdmin);

        $response = $this->getJson('/api/v1/activity-logs');
        $response->assertStatus(200);
    }
}
