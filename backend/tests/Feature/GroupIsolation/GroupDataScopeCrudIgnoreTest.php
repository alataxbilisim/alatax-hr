<?php

namespace Tests\Feature\GroupIsolation;

use App\Enums\CompanyStatus;
use App\Enums\DataScopeLevel;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\DataScopeService;
use App\Support\CompanyContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * G2 — DataScopeLevel::Group CRUD'da yok sayılır (yalnız aktif şirket).
 */
class GroupDataScopeCrudIgnoreTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $user;

    private Employee $employeeA;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $org = Organization::query()->create(['name' => 'Org G2 CRUD', 'slug' => 'org-g2-crud']);
        $this->companyA = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
            'slug' => 'g2-crud-a',
        ]);
        $this->companyB = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
            'slug' => 'g2-crud-b',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::User,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $role = Role::findOrCreate('g2_group_role', 'sanctum');
        // DB CHECK group değerini tutmuyor; resolve config default ile Group olur (G2 CRUD ignore).
        $role->forceFill(['data_scope' => null])->save();
        config(['data-scope.defaults.g2_group_role' => DataScopeLevel::Group->value]);
        $role->givePermissionTo([
            'employees.list.view',
            'employees.list.create',
            'reports.scope.group',
            'reports.definitions.view',
            'reports.definitions.run',
        ]);
        $this->user->assignRole($role);
        app(CompanyContextService::class)->ensureMembership($this->user, (int) $this->companyB->id, false);

        $this->employeeA = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'active',
        ]);
        $this->employeeB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'status' => 'active',
        ]);
    }

    public function test_group_data_scope_resolves_but_employee_list_stays_active_company(): void
    {
        $this->assertSame(
            DataScopeLevel::Group,
            app(DataScopeService::class)->resolve($this->user->fresh()->load('roles'))
        );

        Sanctum::actingAs($this->user->fresh()->load('roles'));
        $ids = $this->getJson('/api/v1/employees', ['X-Company-Id' => (string) $this->companyA->id])
            ->assertOk()
            ->json('data');

        $employeeIds = collect($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($this->employeeA->id, $employeeIds);
        $this->assertNotContains($this->employeeB->id, $employeeIds);
    }

    public function test_group_data_scope_user_sees_b_in_report_with_scope_group(): void
    {
        Sanctum::actingAs($this->user->fresh()->load('roles'));

        $ids = CompanyContext::run($this->companyA->id, function () {
            $result = app(\App\Services\Reports\ReportQueryBuilder::class)->run(
                $this->user->fresh()->load('roles'),
                $this->companyA->id,
                [
                    'dataset' => 'employees',
                    'fields' => ['id'],
                    'scope' => 'group',
                    'limit' => 200,
                ]
            );

            return collect($result['rows'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        });

        $this->assertContains($this->employeeA->id, $ids);
        $this->assertContains($this->employeeB->id, $ids);
    }
}
