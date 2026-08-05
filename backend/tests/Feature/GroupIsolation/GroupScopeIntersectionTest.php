<?php

namespace Tests\Feature\GroupIsolation;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Support\CompanyContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * G2 kapanış — org ∩ membership: aynı org'da membership'siz şirket satırı gelmez.
 */
class GroupScopeIntersectionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Company $companyA;

    private Company $companyB;

    private Company $companyD;

    private User $admin;

    private Employee $employeeA;

    private Employee $employeeB;

    private Employee $employeeD;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->org = Organization::query()->create(['name' => 'Org Intersect', 'slug' => 'org-intersect-g2']);
        $this->companyA = Company::factory()->create([
            'name' => 'Hotel A',
            'slug' => 'demo-otel-a-ix',
            'status' => CompanyStatus::Active,
            'organization_id' => $this->org->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'Hotel B',
            'slug' => 'demo-otel-b-ix',
            'status' => CompanyStatus::Active,
            'organization_id' => $this->org->id,
        ]);
        // Aynı org; admin membership YOK (demo-otel-d senaryosu)
        $this->companyD = Company::factory()->create([
            'name' => 'Hotel D (no membership)',
            'slug' => 'demo-otel-d-ix',
            'status' => CompanyStatus::Active,
            'organization_id' => $this->org->id,
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $this->assignSpatieAdminRole($this->admin->fresh());
        app(CompanyContextService::class)->ensureMembership($this->admin, (int) $this->companyB->id, false);
        // companyD: membership yok — bilerek

        $this->employeeA = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'active',
            'employee_code' => 'IX-A',
        ]);
        $this->employeeB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'status' => 'active',
            'employee_code' => 'IX-B',
        ]);
        $this->employeeD = Employee::factory()->create([
            'company_id' => $this->companyD->id,
            'status' => 'active',
            'employee_code' => 'IX-D',
        ]);
    }

    public function test_scope_group_excludes_same_org_company_without_membership(): void
    {
        Sanctum::actingAs($this->admin);

        $reportable = app(\App\Services\GroupScopeService::class)
            ->reportableCompanyIds($this->admin, (int) $this->companyA->id);
        $this->assertContains($this->companyA->id, $reportable);
        $this->assertContains($this->companyB->id, $reportable);
        $this->assertNotContains($this->companyD->id, $reportable);

        $ids = CompanyContext::run($this->companyA->id, function () {
            $result = app(\App\Services\Reports\ReportQueryBuilder::class)->run(
                $this->admin,
                $this->companyA->id,
                [
                    'dataset' => 'employees',
                    'fields' => ['id', 'company_id'],
                    'scope' => 'group',
                    'limit' => 200,
                ]
            );

            return collect($result['rows']);
        });

        $rowIds = $ids->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($this->employeeA->id, $rowIds);
        $this->assertContains($this->employeeB->id, $rowIds);
        $this->assertNotContains(
            $this->employeeD->id,
            $rowIds,
            'Aynı org membership’siz şirket (D) satırı scope=group’ta olmamalı'
        );

        $companyIdsInRows = $ids->pluck('company_id')->map(fn ($id) => (int) $id)->unique()->all();
        $this->assertNotContains($this->companyD->id, $companyIdsInRows);
    }

    public function test_dashboard_group_kpi_excludes_non_member_org_company(): void
    {
        User::factory()->create([
            'company_id' => $this->companyD->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $countA = User::query()->where('company_id', $this->companyA->id)->where('is_active', true)->count();
        $countB = User::query()->where('company_id', $this->companyB->id)->where('is_active', true)->count();
        $countD = User::query()->where('company_id', $this->companyD->id)->where('is_active', true)->count();
        $this->assertGreaterThan(0, $countD);

        $res = $this->getJson(
            '/api/v1/dashboard?scope=group',
            ['X-Company-Id' => (string) $this->companyA->id]
        )->assertOk();

        $this->assertSame('group', $res->json('data.report_scope'));
        $kpiIds = $res->json('data.company_ids');
        $this->assertContains($this->companyA->id, $kpiIds);
        $this->assertContains($this->companyB->id, $kpiIds);
        $this->assertNotContains($this->companyD->id, $kpiIds);

        $totalUsers = (int) $res->json('data.stats.total_users');
        $this->assertSame($countA + $countB, $totalUsers);
        $this->assertNotSame($countA + $countB + $countD, $totalUsers);
    }
}
