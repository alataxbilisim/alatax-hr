<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use App\Services\Reports\ReportQueryBuilder;
use App\Support\CompanyContext;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz6 öncesi — aynı adlı iki pozisyon position_id ile ayrılır; gruplamada iki satır.
 */
class EmployeePositionIdPersistTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_named_positions_by_id_remain_distinct_in_report_group(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin->fresh());

        $posA = Position::create([
            'company_id' => $company->id,
            'code' => 'POS_A',
            'name' => 'Kıdemli Yazılım Geliştirici',
            'is_active' => true,
        ]);
        $posB = Position::create([
            'company_id' => $company->id,
            'code' => 'POS_B',
            'name' => 'Kıdemli Yazılım Geliştirici',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin->fresh());

        $a = $this->postJson('/api/v1/employees', [
            'employee_code' => 'F6-POS-A',
            'name' => 'Personel A',
            'position_id' => $posA->id,
            'status' => 'active',
        ])->assertStatus(201);

        $b = $this->postJson('/api/v1/employees', [
            'employee_code' => 'F6-POS-B',
            'name' => 'Personel B',
            'position_id' => $posB->id,
            'status' => 'active',
        ])->assertStatus(201);

        $this->assertSame($posA->id, (int) $a->json('data.position_id'));
        $this->assertSame($posB->id, (int) $b->json('data.position_id'));
        $this->assertSame('POS_A', $a->json('data.position'));
        $this->assertSame('POS_B', $b->json('data.position'));
        $this->assertSame('Kıdemli Yazılım Geliştirici', $a->json('data.position_label'));
        $this->assertSame('Kıdemli Yazılım Geliştirici', $b->json('data.position_label'));
        $this->assertNotSame($a->json('data.position_id'), $b->json('data.position_id'));

        $idA = (int) $a->json('data.id');
        $idB = (int) $b->json('data.id');

        $this->getJson("/api/v1/employees/{$idA}")
            ->assertOk()
            ->assertJsonPath('data.employee.position_id', $posA->id)
            ->assertJsonPath('data.employee.position', 'POS_A');
        $this->getJson("/api/v1/employees/{$idB}")
            ->assertOk()
            ->assertJsonPath('data.employee.position_id', $posB->id)
            ->assertJsonPath('data.employee.position', 'POS_B');

        $result = CompanyContext::run($company->id, function () use ($admin) {
            return app(ReportQueryBuilder::class)->run($admin->fresh(), (int) CompanyContext::id(), [
                'dataset' => 'employees',
                'fields' => ['position_id', 'position_name'],
                'group_by' => ['position_id', 'position_name'],
                'aggregations' => [
                    ['fn' => 'count', 'field' => '*', 'alias' => 'adet'],
                ],
                'limit' => 50,
            ]);
        });

        $rows = $result['rows'] ?? [];
        $this->assertCount(2, $rows, 'Aynı adlı iki pozisyon gruplamada iki satır olmalı');

        $ids = collect($rows)->pluck('position_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $this->assertSame([(int) $posA->id, (int) $posB->id], $ids);
        foreach ($rows as $row) {
            $this->assertSame('Kıdemli Yazılım Geliştirici', $row['position_name'] ?? null);
            $this->assertSame(1, (int) ($row['adet'] ?? 0));
        }
    }
}
