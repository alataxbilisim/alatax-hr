<?php

namespace Tests\Feature\Api;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Lookup;
use App\Models\User;
use App\Services\LookupService;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $plainUser;

    private Company $company;

    private Company $otherCompany;

    private LookupService $lookups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->adminUser);
        $this->adminUser = $this->adminUser->fresh();

        $this->plainUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);

        $this->lookups = app(LookupService::class);
    }

    /** @test */
    public function unauthenticated_cannot_read_lookups(): void
    {
        $this->getJson('/api/v1/lookups/employee_status')->assertStatus(401);
    }

    /** @test */
    public function system_seed_includes_currency_city_blood(): void
    {
        $this->assertDatabaseHas('lookups', [
            'lookup_type' => 'currency',
            'value' => 'TRY',
            'is_system' => true,
            'company_id' => null,
        ]);
        $this->assertDatabaseHas('lookups', [
            'lookup_type' => 'city_tr',
            'value' => 'İstanbul',
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('lookups', [
            'lookup_type' => 'blood_type',
            'value' => 'A Rh+',
            'is_system' => true,
        ]);
        $this->assertGreaterThanOrEqual(81, Lookup::where('lookup_type', 'city_tr')->count());
    }

    /** @test */
    public function for_type_returns_merged_active_values(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/lookups/employee_status');
        $response->assertOk()->assertJsonPath('success', true);

        $values = collect($response->json('data'))->pluck('value')->all();
        $this->assertContains('active', $values);
        $this->assertContains('on_leave', $values);
    }

    /** @test */
    public function manage_list_accepts_query_string_false_and_returns_employee_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Axios boolean → "false" string (eski FE bug); normalize sonrası 200
        $asFalseString = $this->getJson(
            '/api/v1/lookups-manage?lookup_type=employee_status&active_only=false'
        );
        $asFalseString->assertOk();
        $values = collect($asFalseString->json('data'))->pluck('value')->all();
        $this->assertContains('active', $values);
        $this->assertContains('on_leave', $values);
        $this->assertContains('suspended', $values);

        $asZero = $this->getJson(
            '/api/v1/lookups-manage?lookup_type=employee_status&active_only=0'
        );
        $asZero->assertOk();
        $this->assertNotEmpty($asZero->json('data'));
    }

    /** @test */
    public function resolve_returns_label_for_value(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/lookups-resolve?lookup_type=employee_status&value=active');
        $response->assertOk()
            ->assertJsonPath('data.value', 'active')
            ->assertJsonPath('data.label', 'Aktif');
    }

    /** @test */
    public function ka_rename_label_reflects_on_existing_employee_without_changing_status_value(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $default = Lookup::whereNull('company_id')
            ->where('lookup_type', 'employee_status')
            ->where('value', 'active')
            ->firstOrFail();

        $this->putJson("/api/v1/lookups-manage/{$default->id}", [
            'label' => 'Çalışıyor',
        ])->assertOk();

        $this->assertSame('active', $employee->fresh()->status);

        $show = $this->getJson("/api/v1/employees/{$employee->id}");
        $show->assertOk();
        $payload = $show->json('data.employee') ?? $show->json('data');
        $this->assertSame('active', $payload['status']);
        $this->assertSame('Çalışıyor', $payload['status_label']);
    }

    /** @test */
    public function kb_used_value_is_deactivated_not_hard_deleted(): void
    {
        Sanctum::actingAs($this->adminUser);

        Employee::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'active',
        ]);

        $default = Lookup::whereNull('company_id')
            ->where('lookup_type', 'employee_status')
            ->where('value', 'active')
            ->firstOrFail();

        $response = $this->deleteJson("/api/v1/lookups-manage/{$default->id}");
        $response->assertOk();
        $this->assertStringContainsString('pasif', mb_strtolower($response->json('message')));

        $this->assertDatabaseHas('lookups', [
            'company_id' => $this->company->id,
            'lookup_type' => 'employee_status',
            'value' => 'active',
            'is_active' => false,
        ]);

        // Platform default hala duruyor
        $this->assertDatabaseHas('lookups', [
            'company_id' => null,
            'lookup_type' => 'employee_status',
            'value' => 'active',
            'is_active' => true,
        ]);

        // Yeni form listesinde active yok
        $forType = $this->getJson('/api/v1/lookups/employee_status');
        $values = collect($forType->json('data'))->pluck('value')->all();
        $this->assertNotContains('active', $values);
    }

    /** @test */
    public function kb_unused_company_value_can_be_hard_deleted(): void
    {
        Sanctum::actingAs($this->adminUser);

        $created = $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'employee_status',
            'value' => 'contractor',
            'label' => 'Taşeron',
            'sort_order' => 90,
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->deleteJson("/api/v1/lookups-manage/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Lookup değeri silindi');

        $this->assertDatabaseMissing('lookups', [
            'id' => $id,
        ]);
    }

    /** @test */
    public function system_lookup_update_returns_403(): void
    {
        Sanctum::actingAs($this->adminUser);

        $currency = Lookup::where('lookup_type', 'currency')->where('value', 'TRY')->firstOrFail();

        $this->putJson("/api/v1/lookups-manage/{$currency->id}", [
            'label' => 'Türk Lirası Hack',
        ])->assertStatus(403);

        $this->deleteJson("/api/v1/lookups-manage/{$currency->id}")->assertStatus(403);
    }

    /** @test */
    public function work_type_shared_by_employee_and_job_position(): void
    {
        Sanctum::actingAs($this->adminUser);

        $employeeTypes = collect($this->getJson('/api/v1/lookups/work_type')->json('data'))->pluck('value');
        $this->assertTrue($employeeTypes->contains('full_time'));
        $this->assertTrue($employeeTypes->contains('internship'));
        $this->assertTrue($employeeTypes->contains('hybrid'));

        $this->lookups->assertValid(LookupService::TYPE_WORK_TYPE, 'internship', $this->company->id, 'employment_type');
        $this->lookups->assertValid(LookupService::TYPE_WORK_TYPE, 'hybrid', $this->company->id, 'work_type');
    }

    /** @test */
    public function management_permission_enforced_on_crud(): void
    {
        Sanctum::actingAs($this->plainUser);

        $this->getJson('/api/v1/lookups-manage?lookup_type=employee_status')->assertStatus(403);
        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'employee_status',
            'label' => 'X',
            'value' => 'x_status',
        ])->assertStatus(403);

        // Form okuma (forType) auth ile açık
        $this->getJson('/api/v1/lookups/employee_status')->assertOk();
    }

    /** @test */
    public function tenant_isolation_on_company_override(): void
    {
        Sanctum::actingAs($this->adminUser);

        $default = Lookup::whereNull('company_id')
            ->where('lookup_type', 'employee_status')
            ->where('value', 'active')
            ->firstOrFail();

        $this->putJson("/api/v1/lookups-manage/{$default->id}", [
            'label' => 'FirmaA Aktif',
        ])->assertOk();

        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);

        Sanctum::actingAs($otherAdmin->fresh());
        $label = $this->getJson('/api/v1/lookups-resolve?lookup_type=employee_status&value=active')
            ->json('data.label');

        $this->assertSame('Aktif', $label);
    }

    /** @test */
    public function unauthorized_role_cannot_manage_even_with_company_admin_type_without_permission(): void
    {
        // company_admin type ama lookups permission yok
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        // Rol yok / permission yok
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'employee_status',
            'value' => 'temp_x',
            'label' => 'Temp',
        ]);

        // company_admin middleware geçebilir; permission middleware 403
        $this->assertContains($response->status(), [403, 401]);
    }

    /** @test */
    public function grup1_personel_lookup_types_are_seeded(): void
    {
        Sanctum::actingAs($this->adminUser);

        foreach (['gender', 'marital_status', 'education_level', 'emergency_relation', 'contract_type', 'employee_document_category', 'blood_type', 'currency'] as $type) {
            $values = collect($this->getJson("/api/v1/lookups/{$type}")->assertOk()->json('data'))->pluck('value');
            $this->assertNotEmpty($values, $type);
        }

        $genders = collect($this->getJson('/api/v1/lookups/gender')->json('data'))->pluck('value');
        $this->assertTrue($genders->contains('male'));
        $this->assertTrue($genders->contains('female'));
    }

    /** @test */
    public function hybrid_leave_request_status_cannot_add_or_delete_value(): void
    {
        Sanctum::actingAs($this->adminUser);

        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'leave_request_status',
            'value' => 'draft',
            'label' => 'Taslak',
        ])->assertStatus(403);

        $pending = Lookup::whereNull('company_id')
            ->where('lookup_type', 'leave_request_status')
            ->where('value', 'pending')
            ->firstOrFail();

        $this->deleteJson("/api/v1/lookups-manage/{$pending->id}")->assertStatus(403);

        // Etiket override serbest
        $this->putJson("/api/v1/lookups-manage/{$pending->id}", [
            'label' => 'Onay Bekliyor',
        ])->assertOk();

        $this->assertSame(
            'Onay Bekliyor',
            $this->getJson('/api/v1/lookups-resolve?lookup_type=leave_request_status&value=pending')
                ->json('data.label')
        );
    }

    /** @test */
    public function hybrid_leave_gender_restriction_cannot_add_value(): void
    {
        Sanctum::actingAs($this->adminUser);

        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'leave_gender_restriction',
            'value' => 'other',
            'label' => 'Diğer',
        ])->assertStatus(403);

        $values = collect($this->getJson('/api/v1/lookups/leave_gender_restriction')->json('data'))->pluck('value');
        $this->assertEqualsCanonicalizing(['all', 'male', 'female'], $values->all());
    }

    /** @test */
    public function application_stage_hybrid_matches_job_application_status_enum(): void
    {
        Sanctum::actingAs($this->adminUser);

        $values = collect($this->getJson('/api/v1/lookups/application_stage')->assertOk()->json('data'))
            ->pluck('value')
            ->all();

        foreach ([
            'new', 'reviewing', 'shortlisted', 'interview_scheduled', 'interviewed',
            'offer_sent', 'hired', 'rejected', 'withdrawn',
        ] as $code) {
            $this->assertContains($code, $values);
        }

        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'application_stage',
            'value' => 'screening',
            'label' => 'Tarama',
        ])->assertStatus(403);

        $new = Lookup::whereNull('company_id')
            ->where('lookup_type', 'application_stage')
            ->where('value', 'new')
            ->firstOrFail();

        $this->putJson("/api/v1/lookups-manage/{$new->id}", [
            'label' => 'Yeni Başvuru',
            'color' => '#abcdef',
        ])->assertOk();

        $this->assertSame(
            'Yeni Başvuru',
            $this->getJson('/api/v1/lookups-resolve?lookup_type=application_stage&value=new')
                ->json('data.label')
        );
    }

    /** @test */
    public function asset_status_and_condition_seeded_with_disposed(): void
    {
        Sanctum::actingAs($this->adminUser);

        $statusValues = collect($this->getJson('/api/v1/lookups/asset_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['available', 'assigned', 'maintenance', 'disposed'],
            $statusValues
        );
        $this->assertNotContains('retired', $statusValues);

        $conditionValues = collect($this->getJson('/api/v1/lookups/asset_condition')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['new', 'good', 'fair', 'poor', 'broken'],
            $conditionValues
        );

        $this->lookups->assertValid(LookupService::TYPE_ASSET_STATUS, 'disposed', $this->company->id, 'status');
        $this->lookups->assertValid(LookupService::TYPE_ASSET_CONDITION, 'broken', $this->company->id, 'condition');
    }

    /** @test */
    public function expense_claim_status_hybrid_matches_model_constants(): void
    {
        Sanctum::actingAs($this->adminUser);

        $values = collect($this->getJson('/api/v1/lookups/expense_claim_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['draft', 'submitted', 'approved', 'rejected', 'paid'],
            $values
        );

        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'expense_claim_status',
            'value' => 'archived',
            'label' => 'Arşiv',
        ])->assertStatus(403);

        $draft = Lookup::whereNull('company_id')
            ->where('lookup_type', 'expense_claim_status')
            ->where('value', 'draft')
            ->firstOrFail();

        $this->putJson("/api/v1/lookups-manage/{$draft->id}", [
            'label' => 'Taslak Kayıt',
        ])->assertOk();

        $this->assertSame(
            'Taslak Kayıt',
            $this->getJson('/api/v1/lookups-resolve?lookup_type=expense_claim_status&value=draft')
                ->json('data.label')
        );
    }

    /** @test */
    public function employee_request_priority_and_status_seeded(): void
    {
        Sanctum::actingAs($this->adminUser);

        $priorities = collect($this->getJson('/api/v1/lookups/employee_request_priority')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(['low', 'normal', 'high', 'urgent'], $priorities);

        $statuses = collect($this->getJson('/api/v1/lookups/employee_request_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['pending', 'in_review', 'approved', 'rejected', 'cancelled'],
            $statuses
        );

        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'employee_request_status',
            'value' => 'escalated',
            'label' => 'Eskalasyon',
        ])->assertStatus(403);

        $this->lookups->assertValid(
            LookupService::TYPE_EMPLOYEE_REQUEST_PRIORITY,
            'urgent',
            $this->company->id,
            'priority'
        );
        $this->lookups->assertValid(
            LookupService::TYPE_EMPLOYEE_REQUEST_STATUS,
            'in_review',
            $this->company->id,
            'status'
        );
    }

    /** @test */
    public function performance_and_onboarding_lookups_seeded(): void
    {
        Sanctum::actingAs($this->adminUser);

        $periodStatuses = collect($this->getJson('/api/v1/lookups/performance_period_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(['draft', 'active', 'closed'], $periodStatuses);

        $reviewStatuses = collect($this->getJson('/api/v1/lookups/performance_review_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(['draft', 'submitted', 'approved', 'rejected'], $reviewStatuses);

        $feedbackTypes = collect($this->getJson('/api/v1/lookups/continuous_feedback_type')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(['praise', 'suggestion', 'concern', 'coaching'], $feedbackTypes);
        $this->assertNotContains('appreciation', $feedbackTypes);
        $this->assertNotContains('other', $feedbackTypes);

        $processStatuses = collect($this->getJson('/api/v1/lookups/onboarding_process_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['pending', 'in_progress', 'completed', 'cancelled'],
            $processStatuses
        );

        $taskStatuses = collect($this->getJson('/api/v1/lookups/onboarding_task_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['pending', 'in_progress', 'completed', 'skipped'],
            $taskStatuses
        );

        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'performance_period_status',
            'value' => 'archived',
            'label' => 'Arşiv',
        ])->assertStatus(403);

        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'onboarding_task_status',
            'value' => 'blocked',
            'label' => 'Bloke',
        ])->assertStatus(403);

        $this->lookups->assertValid(
            LookupService::TYPE_PERFORMANCE_PERIOD_STATUS,
            'active',
            $this->company->id,
            'status'
        );
        $this->lookups->assertValid(
            LookupService::TYPE_PERFORMANCE_REVIEW_STATUS,
            'submitted',
            $this->company->id,
            'status'
        );
        $this->lookups->assertValid(
            LookupService::TYPE_CONTINUOUS_FEEDBACK_TYPE,
            'praise',
            $this->company->id,
            'type'
        );
        $this->lookups->assertValid(
            LookupService::TYPE_ONBOARDING_PROCESS_STATUS,
            'in_progress',
            $this->company->id,
            'status'
        );
        $this->lookups->assertValid(
            LookupService::TYPE_ONBOARDING_TASK_STATUS,
            'skipped',
            $this->company->id,
            'status'
        );
    }

    /** @test */
    public function document_training_survey_lookups_seeded(): void
    {
        Sanctum::actingAs($this->adminUser);

        $approval = collect($this->getJson('/api/v1/lookups/document_approval_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(['draft', 'pending', 'approved', 'rejected'], $approval);

        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'document_approval_status',
            'value' => 'archived',
            'label' => 'Arşiv',
        ])->assertStatus(403);

        $fileTypes = collect($this->getJson('/api/v1/lookups/document_file_type')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['pdf', 'image', 'document', 'spreadsheet', 'presentation', 'archive'],
            $fileTypes
        );
        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'document_file_type',
            'value' => 'video',
            'label' => 'Video',
        ])->assertStatus(403);

        $empDocStatus = collect($this->getJson('/api/v1/lookups/employee_document_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(['active', 'archived', 'expired'], $empDocStatus);

        $trainingTypes = collect($this->getJson('/api/v1/lookups/training_type')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(['online', 'classroom', 'hybrid'], $trainingTypes);

        $sessionStatuses = collect($this->getJson('/api/v1/lookups/training_session_status')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['scheduled', 'in_progress', 'completed', 'cancelled'],
            $sessionStatuses
        );
        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'training_session_status',
            'value' => 'postponed',
            'label' => 'Ertelendi',
        ])->assertStatus(403);

        $categories = collect($this->getJson('/api/v1/lookups/training_category')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['general', 'technical', 'soft_skills', 'compliance', 'leadership', 'safety'],
            $categories
        );

        $surveyTypes = collect($this->getJson('/api/v1/lookups/survey_type')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['engagement', 'satisfaction', 'pulse', 'enps', 'onboarding', 'exit', 'custom'],
            $surveyTypes
        );

        $questionTypes = collect($this->getJson('/api/v1/lookups/survey_question_type')->assertOk()->json('data'))
            ->pluck('value')
            ->all();
        $this->assertEqualsCanonicalizing(
            ['single_choice', 'multiple_choice', 'rating', 'nps', 'text', 'scale', 'matrix'],
            $questionTypes
        );
        $this->postJson('/api/v1/lookups-manage', [
            'lookup_type' => 'survey_question_type',
            'value' => 'file_upload',
            'label' => 'Dosya',
        ])->assertStatus(403);

        $this->lookups->assertValid(LookupService::TYPE_DOCUMENT_APPROVAL_STATUS, 'pending', $this->company->id, 'approval_status');
        $this->lookups->assertValid(LookupService::TYPE_EMPLOYEE_DOCUMENT_STATUS, 'archived', $this->company->id, 'status');
        $this->lookups->assertValid(LookupService::TYPE_TRAINING_TYPE, 'hybrid', $this->company->id, 'type');
        $this->lookups->assertValid(LookupService::TYPE_TRAINING_CATEGORY, 'safety', $this->company->id, 'category');
        $this->lookups->assertValid(LookupService::TYPE_TRAINING_SESSION_STATUS, 'in_progress', $this->company->id, 'status');
        $this->lookups->assertValid(LookupService::TYPE_SURVEY_TYPE, 'enps', $this->company->id, 'type');
        $this->lookups->assertValid(LookupService::TYPE_SURVEY_QUESTION_TYPE, 'matrix', $this->company->id, 'question_type');
        $this->lookups->assertValid(LookupService::TYPE_DOCUMENT_FILE_TYPE, 'pdf', $this->company->id, 'file_type');
    }
}
