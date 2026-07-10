<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class SurveyTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $portalUser;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create(['is_active' => true]);
        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => 'company_admin',
        ]);
        $this->portalUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => 'user',
        ]);
    }

    /** @test */
    public function admin_can_create_survey(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/surveys', [
            'title' => 'Çalışan Memnuniyet Anketi',
            'description' => 'Yıllık memnuniyet anketi',
            'type' => 'satisfaction',
            'is_anonymous' => true,
            'is_active' => true,
            'questions' => [
                [
                    'question_text' => 'Genel memnuniyetiniz nedir?',
                    'question_type' => 'rating',
                    'is_required' => true,
                    'options' => [],
                    'order' => 1,
                ],
                [
                    'question_text' => 'En çok neyi iyileştirebiliriz?',
                    'question_type' => 'text',
                    'is_required' => false,
                    'options' => [],
                    'order' => 2,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('surveys', [
            'company_id' => $this->company->id,
            'title' => 'Çalışan Memnuniyet Anketi',
        ]);
    }

    /** @test */
    public function admin_can_list_surveys(): void
    {
        Sanctum::actingAs($this->adminUser);

        Survey::factory()->count(3)->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->getJson('/api/v1/surveys');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'title', 'type', 'is_active'],
                    ],
                ],
            ]);
    }

    /** @test */
    public function admin_can_update_survey(): void
    {
        Sanctum::actingAs($this->adminUser);

        $survey = Survey::factory()->create([
            'company_id' => $this->company->id,
            'title' => 'Old Title',
        ]);

        $response = $this->putJson("/api/v1/surveys/{$survey->id}", [
            'title' => 'New Title',
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('surveys', [
            'id' => $survey->id,
            'title' => 'New Title',
            'is_active' => false,
        ]);
    }

    /** @test */
    public function admin_can_delete_survey(): void
    {
        Sanctum::actingAs($this->adminUser);

        $survey = Survey::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->deleteJson("/api/v1/surveys/{$survey->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('surveys', [
            'id' => $survey->id,
        ]);
    }

    /** @test */
    public function portal_user_can_list_available_surveys(): void
    {
        Sanctum::actingAs($this->portalUser);

        Survey::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/portal/surveys');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'title', 'type', 'is_anonymous'],
                    ],
                ],
            ]);
    }

    /** @test */
    public function portal_user_can_view_survey_details(): void
    {
        Sanctum::actingAs($this->portalUser);

        $survey = Survey::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        SurveyQuestion::factory()->count(3)->create([
            'survey_id' => $survey->id,
        ]);

        $response = $this->getJson("/api/v1/portal/surveys/{$survey->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'questions' => [
                        '*' => ['id', 'question_text', 'question_type', 'is_required'],
                    ],
                ],
            ]);
    }

    /** @test */
    public function portal_user_can_submit_survey_response(): void
    {
        Sanctum::actingAs($this->portalUser);

        $survey = Survey::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $question = SurveyQuestion::factory()->create([
            'survey_id' => $survey->id,
            'question_type' => 'rating',
            'is_required' => true,
        ]);

        $response = $this->postJson("/api/v1/portal/surveys/{$survey->id}/submit", [
            'responses' => [
                [
                    'question_id' => $question->id,
                    'answer_numeric' => 5,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id,
            'user_id' => $this->portalUser->id,
        ]);
    }
}

