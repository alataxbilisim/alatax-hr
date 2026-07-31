<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * QA-3 portal API smoke — sayfaların dayandığı uçlar 5xx vermemeli.
 * FE rota listesi scripts/portal-route-smoke.mjs ile CI'da ayrı doğrulanır.
 */
class PortalRouteApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $dept = Department::create([
            'company_id' => $company->id,
            'name' => 'Smoke',
            'code' => 'SM',
            'is_active' => true,
        ]);
        $this->user = User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::User,
        ]);
        $this->user->assignRole('employee');
        Employee::factory()->forUser($this->user)->create([
            'company_id' => $company->id,
            'department_id' => $dept->id,
            'status' => 'active',
        ]);
        RequestType::create([
            'company_id' => $company->id,
            'name' => 'Smoke Tip',
            'slug' => 'smoke-tip',
            'is_active' => true,
            'requires_approval' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_portal_page_apis_do_not_server_error(): void
    {
        Sanctum::actingAs($this->user->fresh());

        $endpoints = [
            ['GET', '/api/v1/portal/dashboard'],
            ['GET', '/api/v1/portal/profile'],
            ['GET', '/api/v1/portal/leaves'],
            ['GET', '/api/v1/portal/documents'],
            ['GET', '/api/v1/portal/payslips'],
            ['GET', '/api/v1/portal/salary'],
            ['GET', '/api/v1/portal/training'],
            ['GET', '/api/v1/portal/performance'],
            ['GET', '/api/v1/portal/surveys'],
            ['GET', '/api/v1/portal/timesheet/today'],
            ['GET', '/api/v1/portal/expenses'],
            ['GET', '/api/v1/portal/announcements'],
            ['GET', '/api/v1/portal/requests'],
            ['GET', '/api/v1/portal/requests/types'],
            ['GET', '/api/v1/portal/privacy/status'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $this->assertLessThan(
                500,
                $response->status(),
                "Portal smoke 5xx: {$method} {$uri} → {$response->status()}"
            );
        }
    }
}
