<?php

namespace Tests\Feature\Reports;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\DatasetRegistry;
use App\Services\Reports\ReportQueryBuilder;
use App\Support\CompanyContext;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz6 öncesi — gruplamalı sorguda varsayılan sıralama SQL 42803 üretmesin.
 * DatasetRegistry ile senkron data-provider (DatasetRegistryIsolationTest deseni).
 */
class DatasetGroupedQueryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->admin->fresh());

        Employee::create([
            'company_id' => $this->company->id,
            'employee_code' => 'GRP-01',
            'full_name' => 'Grup Test',
            'status' => 'active',
            'position' => 'Uzman',
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function registeredDatasetsWithGroupField(): array
    {
        return [
            'employees' => ['employees', 'position_id'],
            'leave_requests' => ['leave_requests', 'status'],
            'leave_balances' => ['leave_balances', 'year'],
            'expense_claims' => ['expense_claims', 'status'],
            'job_applications' => ['job_applications', 'status'],
            'survey_responses' => ['survey_responses', 'survey_title'],
            'attendance_records' => ['attendance_records', 'status'],
            'assets' => ['assets', 'status'],
            'training_participants' => ['training_participants', 'status'],
            'employee_documents' => ['employee_documents', 'status'],
            'payslips' => ['payslips', 'period'],
        ];
    }

    /**
     * @dataProvider registeredDatasetsWithGroupField
     */
    public function test_grouped_query_runs_for_each_registry_dataset(string $datasetKey, string $groupField): void
    {
        $registry = app(DatasetRegistry::class);
        $this->assertTrue($registry->has($datasetKey), "Registry'de eksik: {$datasetKey}");

        $allKeys = array_keys($registry->all());
        sort($allKeys);
        $providerKeys = array_map(
            fn (array $row) => $row[0],
            array_values(self::registeredDatasetsWithGroupField())
        );
        sort($providerKeys);
        $this->assertSame($allKeys, $providerKeys, 'Yeni dataset eklendi — bu teste probe ekleyin');

        $result = CompanyContext::run($this->company->id, function () use ($datasetKey, $groupField) {
            return app(ReportQueryBuilder::class)->run($this->admin->fresh(), $this->company->id, [
                'dataset' => $datasetKey,
                'fields' => [$groupField],
                'group_by' => [$groupField],
                'aggregations' => [
                    ['fn' => 'count', 'field' => '*', 'alias' => 'adet'],
                ],
                'limit' => 50,
                // sorts yok — varsayılan sıralama group/measure üzerinden seçilmeli
            ]);
        });

        $this->assertIsArray($result['rows']);
        $this->assertArrayHasKey('meta', $result);
    }

    public function test_group_by_position_ignores_unsafe_default_sort_field(): void
    {
        // Eski bug: group_by position + defaultSort employee_code → SQLSTATE 42803
        $result = CompanyContext::run($this->company->id, function () {
            return app(ReportQueryBuilder::class)->run($this->admin->fresh(), $this->company->id, [
                'dataset' => 'employees',
                'fields' => ['position'],
                'group_by' => ['position'],
                'aggregations' => [
                    ['fn' => 'count', 'field' => '*', 'alias' => 'adet'],
                ],
                // Bilerek güvensiz sort — filtrelenmeli, hata vermemeli
                'sorts' => [
                    ['field' => 'employee_code', 'dir' => 'asc'],
                ],
                'limit' => 50,
            ]);
        });

        $this->assertNotEmpty($result['rows']);
        $this->assertSame('Uzman', $result['rows'][0]['position'] ?? null);
    }
}
