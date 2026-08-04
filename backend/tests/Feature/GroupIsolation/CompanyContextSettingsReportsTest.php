<?php

namespace Tests\Feature\GroupIsolation;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Organization;
use App\Models\Payslip;
use App\Models\SettingValue;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySubmission;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\Reports\ReportQueryBuilder;
use App\Services\Settings\Settings;
use App\Services\Settings\SettingsResolver;
use App\Support\CompanyContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Tur4 — P3 ayar çözümleme + P4 rapor dataset CompanyContext.
 */
class CompanyContextSettingsReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private User $userA;

    private Employee $employeeA;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $org = Organization::query()->create(['name' => 'Org SR', 'slug' => 'org-sr-t4']);
        $this->companyA = Company::factory()->create([
            'name' => 'Demo Firma AŞ',
            'slug' => 'sr-a-t4',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);
        $this->companyB = Company::factory()->create([
            'name' => 'Demo Otel C',
            'slug' => 'sr-b-t4',
            'status' => CompanyStatus::Active,
            'organization_id' => $org->id,
        ]);

        $this->userA = User::factory()->create([
            'company_id' => $this->companyA->id,
            'last_company_id' => $this->companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $this->assignSpatieAdminRole($this->userA->fresh());
        app(CompanyContextService::class)->ensureMembership($this->userA, (int) $this->companyB->id, false);

        $this->employeeA = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'status' => 'active',
        ]);
        $this->employeeB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'status' => 'active',
        ]);
    }

    /** @return array<string, string> */
    private function headers(int $companyId): array
    {
        return ['X-Company-Id' => (string) $companyId];
    }

    public function test_p3_settings_resolve_follow_active_context_not_home(): void
    {
        $key = 'leaves.balance.allow_carryover';

        SettingValue::create([
            'company_id' => $this->companyA->id,
            'scope_type' => 'company',
            'scope_id' => null,
            'key' => $key,
            'value' => SettingValue::wrapScalar(false),
            'updated_by' => $this->userA->id,
        ]);
        SettingValue::create([
            'company_id' => $this->companyB->id,
            'scope_type' => 'company',
            'scope_id' => null,
            'key' => $key,
            'value' => SettingValue::wrapScalar(true),
            'updated_by' => $this->userA->id,
        ]);
        app(SettingsResolver::class)->invalidateCompany($this->companyA->id);
        app(SettingsResolver::class)->invalidateCompany($this->companyB->id);

        Sanctum::actingAs($this->userA);

        // scopeFromUser + aktif bağlam B
        $viaScope = CompanyContext::run($this->companyB->id, function () {
            return (bool) Settings::get('leaves.balance.allow_carryover', Settings::scopeFromUser($this->userA));
        });
        $this->assertTrue($viaScope, 'Aktif Otel C (B) ayarı true olmalı');

        $viaHome = CompanyContext::run($this->companyA->id, function () {
            return (bool) Settings::get('leaves.balance.allow_carryover', Settings::scopeFromUser($this->userA));
        });
        $this->assertFalse($viaHome, 'Aktif Firma AŞ (A) ayarı false olmalı');

        // Registry HTTP — X-Company-Id = B (noktalı key için bracket; Arr::get kırar)
        $resB = $this->getJson(
            '/api/v1/settings/values?keys='.$key,
            $this->headers($this->companyB->id)
        )->assertOk();
        $dataB = $resB->json('data');
        $this->assertTrue((bool) ($dataB[$key]['value'] ?? false));

        $resA = $this->getJson(
            '/api/v1/settings/values?keys='.$key,
            $this->headers($this->companyA->id)
        )->assertOk();
        $dataA = $resA->json('data');
        $this->assertFalse((bool) ($dataA[$key]['value'] ?? true));
    }

    public function test_p4_payslips_dataset_excludes_other_company_under_active_context(): void
    {
        $payA = Payslip::query()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => $this->employeeA->id,
            'period' => '2026-01',
            'year' => 2026,
            'month' => 1,
            'gross_salary' => 1000,
            'net_salary' => 800,
            'is_published' => true,
            'created_by' => $this->userA->id,
        ]);
        $payB = Payslip::query()->create([
            'company_id' => $this->companyB->id,
            'employee_id' => $this->employeeB->id,
            'period' => '2026-01',
            'year' => 2026,
            'month' => 1,
            'gross_salary' => 2000,
            'net_salary' => 1600,
            'is_published' => true,
            'created_by' => $this->userA->id,
        ]);

        $ids = $this->runDatasetIds('payslips', $this->companyA->id, ['id', 'employee_id']);
        $this->assertContains($payA->id, $ids);
        $this->assertNotContains($payB->id, $ids);
    }

    public function test_p4_employee_documents_dataset_excludes_other_company_under_active_context(): void
    {
        $docA = EmployeeDocument::query()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => $this->employeeA->id,
            'title' => 'Doc A',
            'category' => 'other',
            'file_path' => 'a.pdf',
            'file_name' => 'a.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 10,
            'uploaded_by' => $this->userA->id,
            'created_by' => $this->userA->id,
        ]);
        $docB = EmployeeDocument::query()->create([
            'company_id' => $this->companyB->id,
            'employee_id' => $this->employeeB->id,
            'title' => 'Doc B',
            'category' => 'other',
            'file_path' => 'b.pdf',
            'file_name' => 'b.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 10,
            'uploaded_by' => $this->userA->id,
            'created_by' => $this->userA->id,
        ]);

        $ids = $this->runDatasetIds('employee_documents', $this->companyA->id, ['id', 'employee_id']);
        $this->assertContains($docA->id, $ids);
        $this->assertNotContains($docB->id, $ids);
    }

    public function test_p4_training_participants_dataset_excludes_other_company_under_active_context(): void
    {
        [$partA, $partB] = $this->seedTrainingParticipants();

        // Home A, aktif bağlam B → yalnız B satırı (constrainQuery CompanyContext)
        $ids = $this->runDatasetIds('training_participants', $this->companyB->id, ['id', 'user_id'], $this->companyB->id);
        $this->assertContains($partB->id, $ids);
        $this->assertNotContains($partA->id, $ids);
    }

    public function test_p4_survey_responses_dataset_excludes_other_company_under_active_context(): void
    {
        [$respA, $respB] = $this->seedSurveyResponses();

        $ids = $this->runDatasetIds('survey_responses', $this->companyB->id, ['id'], $this->companyB->id);
        $this->assertContains($respB->id, $ids);
        $this->assertNotContains($respA->id, $ids);
    }

    /**
     * @param  list<string>  $fields
     * @return list<int>
     */
    private function runDatasetIds(string $dataset, int $contextCompanyId, array $fields, ?int $runCompanyId = null): array
    {
        $runCompanyId ??= $contextCompanyId;

        return CompanyContext::run($contextCompanyId, function () use ($dataset, $fields, $runCompanyId) {
            Sanctum::actingAs($this->userA);
            $result = app(ReportQueryBuilder::class)->run($this->userA, $runCompanyId, [
                'dataset' => $dataset,
                'fields' => $fields,
                'limit' => 100,
            ]);
            $ids = [];
            foreach ($result['rows'] as $row) {
                if (isset($row['id'])) {
                    $ids[] = (int) $row['id'];
                }
            }

            return $ids;
        });
    }

    /** @return array{0: TrainingParticipant, 1: TrainingParticipant} */
    private function seedTrainingParticipants(): array
    {
        $make = function (Company $company, User $participant) {
            $training = Training::query()->create([
                'company_id' => $company->id,
                'title' => 'T-'.$company->id,
                'status' => 'published',
                'created_by' => $this->userA->id,
            ]);
            $session = TrainingSession::query()->create([
                'training_id' => $training->id,
                'start_date' => now()->addDay(),
                'end_date' => now()->addDays(2),
                'status' => 'scheduled',
                'created_by' => $this->userA->id,
            ]);

            return TrainingParticipant::query()->create([
                'session_id' => $session->id,
                'user_id' => $participant->id,
                'status' => 'registered',
                'registered_at' => now(),
            ]);
        };

        $userPartA = User::factory()->create(['company_id' => $this->companyA->id, 'type' => UserType::User]);
        $userPartB = User::factory()->create(['company_id' => $this->companyB->id, 'type' => UserType::User]);

        return [$make($this->companyA, $userPartA), $make($this->companyB, $userPartB)];
    }

    /** @return array{0: SurveyResponse, 1: SurveyResponse} */
    private function seedSurveyResponses(): array
    {
        $make = function (Company $company) {
            $survey = Survey::query()->create([
                'company_id' => $company->id,
                'title' => 'S-'.$company->id,
                'is_active' => true,
                'created_by' => $this->userA->id,
            ]);
            $question = SurveyQuestion::query()->create([
                'survey_id' => $survey->id,
                'question_text' => 'Q?',
                'question_type' => 'text',
                'order_number' => 1,
            ]);
            $submission = SurveySubmission::query()->create([
                'survey_id' => $survey->id,
                'user_id' => $this->userA->id,
                'status' => 'completed',
                'started_at' => now()->subHour(),
                'completed_at' => now(),
            ]);

            return SurveyResponse::query()->create([
                'survey_submission_id' => $submission->id,
                'survey_question_id' => $question->id,
                'answer_text' => 'ans-'.$company->id,
            ]);
        };

        return [$make($this->companyA), $make($this->companyB)];
    }
}
