<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Module;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ 4A-3 Zincir 3a — anket audience filtre + portal erişim.
 */
class SurveyAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Department $deptA;

    private Department $deptB;

    private User $userInAudience;

    private User $userOutside;

    private Survey $survey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);

        $surveysModule = Module::firstOrCreate(
            ['slug' => 'surveys'],
            ['name' => 'Anketler', 'is_core' => false, 'is_active' => true]
        );
        $this->company->modules()->syncWithoutDetaching([
            $surveysModule->id => ['is_active' => true, 'activated_at' => now()],
        ]);

        $this->deptA = Department::create([
            'company_id' => $this->company->id,
            'name' => 'İK',
            'code' => 'HR',
            'is_active' => true,
        ]);
        $this->deptB = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Satış',
            'code' => 'SAL',
            'is_active' => true,
        ]);

        $this->userInAudience = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($this->userInAudience)->create([
            'company_id' => $this->company->id,
            'department_id' => $this->deptA->id,
        ]);

        $this->userOutside = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($this->userOutside)->create([
            'company_id' => $this->company->id,
            'department_id' => $this->deptB->id,
        ]);

        $this->survey = Survey::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'is_anonymous' => false,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'audience' => 'department',
            'audience_filter' => ['department_ids' => [$this->deptA->id]],
        ]);

        SurveyQuestion::factory()->create([
            'survey_id' => $this->survey->id,
            'question_type' => 'text',
            'is_required' => true,
        ]);
    }

    public function test_unauthenticated_portal_surveys_returns_401(): void
    {
        $this->getJson('/api/v1/portal/surveys')->assertStatus(401);
    }

    public function test_audience_outsider_does_not_see_survey_in_list(): void
    {
        Sanctum::actingAs($this->userOutside);

        $response = $this->getJson('/api/v1/portal/surveys');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->survey->id, $ids);
    }

    public function test_audience_outsider_cannot_view_or_start_survey(): void
    {
        Sanctum::actingAs($this->userOutside);

        $this->getJson("/api/v1/portal/surveys/{$this->survey->id}")
            ->assertStatus(403);

        $this->postJson("/api/v1/portal/surveys/{$this->survey->id}/start")
            ->assertStatus(403);
    }

    public function test_audience_insider_sees_survey_and_can_start(): void
    {
        Sanctum::actingAs($this->userInAudience);

        $list = $this->getJson('/api/v1/portal/surveys');
        $list->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains($this->survey->id, $ids);

        $this->getJson("/api/v1/portal/surveys/{$this->survey->id}")
            ->assertOk()
            ->assertJsonPath('data.survey.id', $this->survey->id);

        $this->postJson("/api/v1/portal/surveys/{$this->survey->id}/start")
            ->assertCreated();
    }

    public function test_audience_all_visible_to_both_employees(): void
    {
        $open = Survey::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'audience' => 'all',
            'audience_filter' => null,
        ]);

        Sanctum::actingAs($this->userOutside);
        $ids = collect($this->getJson('/api/v1/portal/surveys')->json('data'))->pluck('id')->all();
        $this->assertContains($open->id, $ids);

        Sanctum::actingAs($this->userInAudience);
        $ids2 = collect($this->getJson('/api/v1/portal/surveys')->json('data'))->pluck('id')->all();
        $this->assertContains($open->id, $ids2);
    }
}
