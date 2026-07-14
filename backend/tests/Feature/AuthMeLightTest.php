<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /auth/me?light=1 — periyodik silent checkAuth için hafif payload.
 */
class AuthMeLightTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->admin = User::factory()->create([
            'type' => UserType::CompanyAdmin,
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($this->admin);
        $this->admin = $this->admin->fresh();
    }

    public function test_me_full_includes_permissions(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/v1/auth/me')->assertOk();

        $permissions = $res->json('data.user.permissions');
        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        $this->assertIsArray($res->json('data.user.company.active_modules'));
    }

    public function test_me_light_skips_permission_dump(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/v1/auth/me?light=1')->assertOk();

        $user = $res->json('data.user');
        $this->assertSame($this->admin->id, $user['id']);
        $this->assertSame([], $user['permissions']);
        $this->assertSame([], $user['company']['active_modules']);
        $this->assertNotEmpty($user['email']);
        $this->assertArrayHasKey('roles', $user);
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/auth/me?light=1')->assertUnauthorized();
    }
}
