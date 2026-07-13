<?php

namespace Tests\Feature\Api;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use App\Services\PositionCatalogSeedService;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * FAZ A5 — Pozisyon kataloğu CRUD, seed, tenant izolasyonu.
 */
class PositionCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    private User $plainUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ModuleSeeder::class);
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        $this->admin = $this->admin->fresh();

        $this->plainUser = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
    }

    /** @test */
    public function unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/positions')->assertStatus(401);
    }

    /** @test */
    public function unauthorized_role_gets_403(): void
    {
        Sanctum::actingAs($this->plainUser);

        $this->getJson('/api/v1/positions')->assertStatus(403);
    }

    /** @test */
    public function seed_creates_system_positions_with_sgk_codes(): void
    {
        $defs = PositionCatalogSeedService::definitions();
        $this->assertGreaterThanOrEqual(30, count($defs));
        $this->assertLessThanOrEqual(60, count($defs));

        app(PositionCatalogSeedService::class)->ensureForCompany($this->company);

        $count = Position::withoutCompanyScope()
            ->where('company_id', $this->company->id)
            ->where('is_system', true)
            ->count();

        $this->assertSame(count($defs), $count);
        $this->assertDatabaseHas('positions', [
            'company_id' => $this->company->id,
            'code' => 'YAZ_GEL',
            'sgk_occupation_code' => '2512.05',
            'is_system' => true,
        ]);
    }

    /** @test */
    public function seed_is_idempotent_and_preserves_custom_name(): void
    {
        $svc = app(PositionCatalogSeedService::class);
        $svc->ensureForCompany($this->company);

        $pos = Position::withoutCompanyScope()
            ->where('company_id', $this->company->id)
            ->where('code', 'YAZ_GEL')
            ->firstOrFail();
        $pos->update(['name' => 'Firma Özel Yazılımcı']);

        $svc->ensureForCompany($this->company);

        $pos->refresh();
        $this->assertSame('Firma Özel Yazılımcı', $pos->name);
        $this->assertSame(
            count(PositionCatalogSeedService::definitions()),
            Position::withoutCompanyScope()->where('company_id', $this->company->id)->where('is_system', true)->count()
        );
    }

    /** @test */
    public function admin_can_crud_positions(): void
    {
        Sanctum::actingAs($this->admin);

        $create = $this->postJson('/api/v1/positions', [
            'code' => 'CUSTOM1',
            'name' => 'Özel Unvan',
            'sgk_occupation_code' => '2512.99',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'CUSTOM1')
            ->assertJsonPath('data.name', 'Özel Unvan')
            ->assertJsonPath('data.sgk_occupation_code', '2512.99')
            ->assertJsonPath('data.is_system', false);

        $id = (int) $create->json('data.id');

        $this->getJson('/api/v1/positions?search=Özel')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson("/api/v1/positions/{$id}", [
            'name' => 'Güncellenmiş Unvan',
            'sgk_occupation_code' => '2512.01',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Güncellenmiş Unvan')
            ->assertJsonPath('data.sgk_occupation_code', '2512.01');

        $this->deleteJson("/api/v1/positions/{$id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('positions', ['id' => $id]);
    }

    /** @test */
    public function company_can_edit_system_position_but_cannot_delete(): void
    {
        app(PositionCatalogSeedService::class)->ensureForCompany($this->company);

        $pos = Position::withoutCompanyScope()
            ->where('company_id', $this->company->id)
            ->where('code', 'IK_UZM')
            ->firstOrFail();

        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/positions/{$pos->id}", [
            'name' => 'İK Uzmanı (Firma)',
            'code' => 'HACKED', // sistem kodu korunmalı
        ])->assertOk()
            ->assertJsonPath('data.name', 'İK Uzmanı (Firma)')
            ->assertJsonPath('data.code', 'IK_UZM');

        $this->deleteJson("/api/v1/positions/{$pos->id}")
            ->assertStatus(422);
    }

    /** @test */
    public function tenant_isolation_blocks_cross_company_access(): void
    {
        $otherPos = Position::factory()->create([
            'company_id' => $this->otherCompany->id,
            'code' => 'OTHER',
            'name' => 'Diğer Firma',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/positions/{$otherPos->id}")->assertStatus(404);
        $this->putJson("/api/v1/positions/{$otherPos->id}", ['name' => 'Hack'])->assertStatus(404);
        $this->deleteJson("/api/v1/positions/{$otherPos->id}")->assertStatus(404);

        $list = $this->getJson('/api/v1/positions')->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertNotContains($otherPos->id, $ids);
    }

    /** @test */
    public function register_seeds_position_catalog(): void
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'company_name' => 'A5 Pozisyon AŞ',
            'name' => 'Admin A5',
            'email' => 'a5.admin@test.local',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertCreated();

        $companyId = $res->json('data.user.company.id');
        $this->assertNotNull($companyId);

        $count = Position::withoutCompanyScope()
            ->where('company_id', $companyId)
            ->where('is_system', true)
            ->count();

        $this->assertSame(count(PositionCatalogSeedService::definitions()), $count);
    }

    /** @test */
    public function user_with_positions_view_can_list(): void
    {
        $perm = Permission::findOrCreate('employees.positions.view', 'sanctum');
        $this->plainUser->givePermissionTo($perm);

        Sanctum::actingAs($this->plainUser->fresh());

        $this->getJson('/api/v1/positions')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
