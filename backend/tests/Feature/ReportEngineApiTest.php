<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\SavedReport;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D1a — Rapor tanımı API: CRUD + preview + run + paylaşım + 401/403.
 */
class ReportEngineApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/reports')->assertUnauthorized();
        $this->getJson('/api/v1/reports/datasets')->assertUnauthorized();
        $this->postJson('/api/v1/reports/preview', ['dataset' => 'employees'])->assertUnauthorized();
    }

    public function test_unauthorized_role_gets_403(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/reports')->assertForbidden();
        $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code'],
        ])->assertForbidden();
    }

    public function test_crud_preview_run_and_share(): void
    {
        Sanctum::actingAs($this->admin->fresh());

        $datasets = $this->getJson('/api/v1/reports/datasets')->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(5, count($datasets));
        $keys = collect($datasets)->pluck('key');
        foreach (['employees', 'leave_requests', 'leave_balances', 'expense_claims', 'job_applications'] as $k) {
            $this->assertTrue($keys->contains($k));
        }

        $create = $this->postJson('/api/v1/reports', [
            'name' => 'Personel Durum',
            'dataset_key' => 'employees',
            'fields' => ['employee_code', 'status'],
            'filters' => [
                ['field' => 'status', 'op' => 'eq', 'value' => 'active'],
            ],
            'is_shared' => false,
        ])->assertCreated();

        $id = (int) $create->json('data.id');
        $this->assertDatabaseHas('saved_reports', [
            'id' => $id,
            'dataset_key' => 'employees',
            'company_id' => $this->company->id,
        ]);

        $this->getJson("/api/v1/reports/{$id}")->assertOk()
            ->assertJsonPath('data.name', 'Personel Durum');

        $this->putJson("/api/v1/reports/{$id}", [
            'name' => 'Personel Durum v2',
            'is_shared' => true,
        ])->assertOk()->assertJsonPath('data.name', 'Personel Durum v2');

        $this->postJson('/api/v1/reports/preview', [
            'dataset' => 'employees',
            'fields' => ['employee_code', 'status'],
            'limit' => 10,
        ])->assertOk()->assertJsonPath('data.meta.dataset', 'employees');

        $this->postJson("/api/v1/reports/{$id}/run", ['limit' => 5])
            ->assertOk()
            ->assertJsonPath('data.meta.dataset', 'employees');

        // Paylaşılan raporu başka kullanıcı kendi kapsamıyla görür
        $peer = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('shared_runner', 'sanctum');
        $role->forceFill(['data_scope' => 'own'])->save();
        $role->givePermissionTo([
            'reports.definitions.view',
            'reports.definitions.run',
        ]);
        $peer->assignRole($role);

        Sanctum::actingAs($peer->fresh());
        $this->getJson("/api/v1/reports/{$id}")->assertOk();
        $run = $this->postJson("/api/v1/reports/{$id}/run")->assertOk();
        $this->assertSame('own', $run->json('data.meta.data_scope'));

        Sanctum::actingAs($this->admin->fresh());
        $this->deleteJson("/api/v1/reports/{$id}")->assertOk();
        $this->assertDatabaseMissing('saved_reports', ['id' => $id]);
    }

    public function test_tenant_cannot_access_other_company_report(): void
    {
        Sanctum::actingAs($this->admin->fresh());
        $id = (int) $this->postJson('/api/v1/reports', [
            'name' => 'Gizli',
            'dataset_key' => 'employees',
            'fields' => ['employee_code'],
            'is_shared' => true,
        ])->assertCreated()->json('data.id');

        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);
        Sanctum::actingAs($otherAdmin->fresh());
        $this->getJson("/api/v1/reports/{$id}")->assertNotFound();
        $this->postJson("/api/v1/reports/{$id}/run")->assertNotFound();
    }

    public function test_legacy_employee_saved_report_without_dataset_key_not_in_engine_list(): void
    {
        SavedReport::create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'name' => 'Eski personel raporu',
            'config' => ['chartType' => 'bar'],
            'dataset_key' => null,
            'is_shared' => false,
        ]);

        Sanctum::actingAs($this->admin->fresh());
        $list = $this->getJson('/api/v1/reports')->assertOk()->json('data');
        $names = collect($list)->pluck('name');
        $this->assertFalse($names->contains('Eski personel raporu'));
    }
}
