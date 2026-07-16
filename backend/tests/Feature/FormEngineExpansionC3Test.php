<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\User;
use App\Services\DefaultLeaveApprovalWorkflowService;
use App\Services\FormFieldCatalogService;
use Database\Seeders\EmployeeFormFieldSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ 4A-5 / C3 — leave_request + expense + asset Form Engine yayılımı.
 */
class FormEngineExpansionC3Test extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    private User $portalUser;

    private LeaveType $leaveType;

    private ExpenseCategory $expenseCategory;

    private AssetCategory $assetCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        $this->seed(EmployeeFormFieldSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->companyA = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->companyB = Company::factory()->create(['status' => CompanyStatus::Active]);

        foreach (['leave-management', 'expense-management', 'asset-management'] as $slug) {
            $this->enableModule($this->companyA, $slug);
            $this->enableModule($this->companyB, $slug);
        }

        $this->adminA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminA);
        $this->adminA = $this->adminA->fresh();

        $this->adminB = User::factory()->create([
            'company_id' => $this->companyB->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminB);
        $this->adminB = $this->adminB->fresh();

        $this->portalUser = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        Employee::factory()->forUser($this->portalUser)->create();

        $this->leaveType = LeaveType::create([
            'company_id' => $this->companyA->id,
            'name' => 'C3 Annual',
            'code' => 'C3-AN',
            'is_active' => true,
            'default_days' => 20,
            'requires_document' => false,
        ]);
        app(DefaultLeaveApprovalWorkflowService::class)->ensureForCompany($this->companyA);

        $this->expenseCategory = ExpenseCategory::create([
            'company_id' => $this->companyA->id,
            'name' => 'Seyahat',
            'code' => 'TRV',
            'is_active' => true,
        ]);

        $this->assetCategory = AssetCategory::create([
            'company_id' => $this->companyA->id,
            'name' => 'Laptop',
            'is_active' => true,
        ]);
    }

    private function enableModule(Company $company, string $slug): void
    {
        $module = Module::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'is_core' => false, 'is_active' => true]
        );
        $company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);
    }

    public function test_expense_and_asset_catalog_seed_idempotent(): void
    {
        $beforeExpense = CustomFieldDefinition::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->where('entity_type', 'expense')
            ->count();
        $beforeAsset = CustomFieldDefinition::withoutGlobalScopes()
            ->whereNull('company_id')
            ->where('is_system', true)
            ->where('entity_type', 'asset')
            ->count();

        $this->assertGreaterThanOrEqual(3, $beforeExpense);
        $this->assertGreaterThanOrEqual(8, $beforeAsset);

        app(FormFieldCatalogService::class)->seedSystemCatalog();
        app(FormFieldCatalogService::class)->seedSystemCatalog();

        $this->assertSame(
            $beforeExpense,
            CustomFieldDefinition::withoutGlobalScopes()
                ->whereNull('company_id')
                ->where('is_system', true)
                ->where('entity_type', 'expense')
                ->count()
        );
        $this->assertSame(
            $beforeAsset,
            CustomFieldDefinition::withoutGlobalScopes()
                ->whereNull('company_id')
                ->where('is_system', true)
                ->where('entity_type', 'asset')
                ->count()
        );
    }

    public function test_form_definitions_expense_asset_401_403_tenant(): void
    {
        $this->getJson('/api/v1/form-definitions/expense')->assertStatus(401);

        $viewer = User::factory()->create([
            'company_id' => $this->companyA->id,
            'type' => UserType::User,
        ]);
        $role = \App\Models\Role::findOrCreate('viewer_no_forms_c3', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->syncPermissions(['leaves.requests.view']);
        $viewer->assignRole($role);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/form-definitions/expense')->assertStatus(403);
        $this->getJson('/api/v1/form-definitions/asset')->assertStatus(403);

        Sanctum::actingAs($this->adminA);
        $this->getJson('/api/v1/form-definitions/expense')
            ->assertStatus(200)
            ->assertJsonPath('data.entity_type', 'expense');
        $this->getJson('/api/v1/form-definitions/asset')
            ->assertStatus(200)
            ->assertJsonPath('data.entity_type', 'asset');

        $this->putJson('/api/v1/form-definitions/expense', [
            'name' => 'Firma Masraf Formu',
            'fields' => [
                ['system_key' => 'title', 'label_override' => 'Masraf Başlığı'],
            ],
        ])->assertStatus(200);

        Sanctum::actingAs($this->adminB);
        $reasonB = collect($this->getJson('/api/v1/form-definitions/expense')->json('data.fields'))
            ->firstWhere('system_key', 'title');
        $this->assertSame('Başlık', $reasonB['effective_label']);
    }

    public function test_leave_request_persists_custom_fields(): void
    {
        CustomFieldDefinition::create([
            'company_id' => $this->companyA->id,
            'entity_type' => CustomFieldDefinition::ENTITY_LEAVE_REQUEST,
            'field_key' => 'trip_note',
            'field_label' => 'Seyahat Notu',
            'field_type' => CustomFieldDefinition::TYPE_TEXT,
            'is_system' => false,
            'is_required' => true,
            'is_active' => true,
            'is_hidden' => false,
            'sort_order' => 900,
        ]);

        LeaveBalance::create([
            'company_id' => $this->companyA->id,
            'user_id' => $this->adminA->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'total_days' => 20,
            'used_days' => 0,
            'pending_days' => 0,
        ]);

        Sanctum::actingAs($this->adminA);

        $this->postJson('/api/v1/leaves/requests', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'reason' => 'C3 test',
            'custom_fields' => ['trip_note' => 'Ankara'],
        ])->assertStatus(201);

        $leave = LeaveRequest::query()->where('user_id', $this->adminA->id)->latest('id')->first();
        $this->assertNotNull($leave);
        $this->assertSame('Ankara', $leave->custom_fields['trip_note'] ?? null);

        $this->postJson('/api/v1/leaves/requests', [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(21)->toDateString(),
            'custom_fields' => [],
        ])->assertStatus(422);
    }

    public function test_expense_and_asset_persist_custom_fields(): void
    {
        CustomFieldDefinition::create([
            'company_id' => $this->companyA->id,
            'entity_type' => CustomFieldDefinition::ENTITY_EXPENSE,
            'field_key' => 'cost_center',
            'field_label' => 'Masraf Merkezi',
            'field_type' => CustomFieldDefinition::TYPE_TEXT,
            'is_system' => false,
            'is_required' => false,
            'is_active' => true,
            'is_hidden' => false,
            'sort_order' => 900,
        ]);
        CustomFieldDefinition::create([
            'company_id' => $this->companyA->id,
            'entity_type' => CustomFieldDefinition::ENTITY_ASSET,
            'field_key' => 'tag_color',
            'field_label' => 'Etiket Rengi',
            'field_type' => CustomFieldDefinition::TYPE_TEXT,
            'is_system' => false,
            'is_required' => false,
            'is_active' => true,
            'is_hidden' => false,
            'sort_order' => 900,
        ]);

        Sanctum::actingAs($this->portalUser);
        $this->postJson('/api/v1/portal/expenses', [
            'title' => 'C3 Masraf',
            'expense_date' => now()->toDateString(),
            'custom_fields' => ['cost_center' => 'M-100'],
            'items' => [
                [
                    'expense_category_id' => $this->expenseCategory->id,
                    'description' => 'Uçak',
                    'item_date' => now()->toDateString(),
                    'amount' => 1500,
                ],
            ],
        ])->assertStatus(201);

        $claim = ExpenseClaim::query()->where('user_id', $this->portalUser->id)->latest('id')->first();
        $this->assertSame('M-100', $claim?->custom_fields['cost_center'] ?? null);

        Sanctum::actingAs($this->adminA);
        $this->postJson('/api/v1/assets/items', [
            'category_id' => $this->assetCategory->id,
            'name' => 'MacBook C3',
            'condition' => 'new',
            'custom_fields' => ['tag_color' => 'blue'],
        ])->assertStatus(201);

        $asset = Asset::query()->where('company_id', $this->companyA->id)->latest('id')->first();
        $this->assertSame('blue', $asset?->custom_fields['tag_color'] ?? null);
    }

    public function test_portal_form_definition_read_only_entities(): void
    {
        Sanctum::actingAs($this->portalUser);
        $this->getJson('/api/v1/portal/form-definitions/leave_request')
            ->assertStatus(200)
            ->assertJsonPath('data.entity_type', 'leave_request');
        $this->getJson('/api/v1/portal/form-definitions/expense')
            ->assertStatus(200)
            ->assertJsonPath('data.entity_type', 'expense');
        $this->getJson('/api/v1/portal/form-definitions/employee')->assertStatus(422);
    }
}
