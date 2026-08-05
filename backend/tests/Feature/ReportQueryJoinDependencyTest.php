<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use App\Services\Reports\ReportQueryBuilder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * QA-3 regresyon: join bağımlılığı (trainings→sessions, surveys→submissions) ve personDistinctColumn.
 */
class ReportQueryJoinDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_title_group_by_resolves_session_join(): void
    {
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $admin = User::factory()->create([
            'home_company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin);
        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();

        Sanctum::actingAs($admin->fresh());
        $result = app(ReportQueryBuilder::class)->run($admin->fresh(), (int) $company->id, [
            'dataset' => 'training_participants',
            'fields' => ['training_title', 'id'],
            'group_by' => ['training_title'],
            'aggregations' => [['field' => 'id', 'fn' => 'count', 'alias' => 'cnt']],
            'sorts' => [['field' => 'training_title', 'dir' => 'asc']],
            'limit' => 5,
        ]);

        $this->assertSame('training_participants', $result['meta']['dataset']);
        $this->assertIsArray($result['rows']);
    }

    public function test_survey_anonymous_aggregate_joins_submissions_for_min_cell(): void
    {
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $admin = User::factory()->create([
            'home_company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin);
        Role::findByName('admin', 'sanctum')->forceFill(['data_scope' => 'company'])->save();

        Sanctum::actingAs($admin->fresh());
        $result = app(ReportQueryBuilder::class)->run($admin->fresh(), (int) $company->id, [
            'dataset' => 'survey_responses',
            'fields' => ['survey_question_id', 'answer_numeric'],
            'group_by' => ['survey_question_id'],
            'aggregations' => [['field' => 'answer_numeric', 'fn' => 'avg', 'alias' => 'avg_answer']],
            'sorts' => [['field' => 'survey_question_id', 'dir' => 'asc']],
            'limit' => 5,
        ]);

        $this->assertSame('survey_responses', $result['meta']['dataset']);
        $this->assertIsArray($result['rows']);
    }
}
