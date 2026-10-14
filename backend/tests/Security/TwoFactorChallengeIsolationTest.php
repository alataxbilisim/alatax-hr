<?php

namespace Tests\Security;

use Database\Seeders\PermissionSeeder;
use Tests\Concerns\CreatesPortalEmployees;
use Tests\Concerns\CreatesTwoFactorUsers;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * K1 — 2FA challenge token'ı yalnız /auth/2fa/verify'a erişebilir;
 * panel, portal ve süper admin uçları reddedilir.
 */
class TwoFactorChallengeIsolationTest extends TestCase
{
    use CreatesPortalEmployees;
    use CreatesTwoFactorUsers;
    use CreatesUsersWithPermissions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_portal_challenge_token_cannot_reach_portal_endpoints(): void
    {
        $user = $this->portalEmployee($this->createActiveCompany());
        $this->enableTwoFactor($user);

        $this->withToken($this->challengeTokenFor($user, portal: true))
            ->getJson('/api/v1/portal/dashboard')
            ->assertStatus(403);
    }

    public function test_portal_real_token_still_reaches_portal(): void
    {
        $user = $this->portalEmployee($this->createActiveCompany());

        $this->withToken($user->createToken('portal-token', ['portal:access'])->plainTextToken)
            ->getJson('/api/v1/portal/profile')
            ->assertOk();
    }

    public function test_super_admin_challenge_token_cannot_reach_admin_endpoints(): void
    {
        $admin = $this->superAdminUser();
        $this->enableTwoFactor($admin);

        $this->withToken($this->challengeTokenFor($admin))
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_super_admin_real_token_still_reaches_admin(): void
    {
        $admin = $this->superAdminUser();

        $this->withToken($admin->createToken('auth-token')->plainTextToken)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk();
    }

    public function test_panel_challenge_token_cannot_reach_panel_endpoints(): void
    {
        $user = $this->companyAdminFor($this->createActiveCompany());
        $this->enableTwoFactor($user);

        $this->withToken($this->challengeTokenFor($user))
            ->getJson('/api/v1/dashboard')
            ->assertStatus(403);
    }

    public function test_challenge_token_can_still_call_verify(): void
    {
        $user = $this->portalEmployee($this->createActiveCompany());
        $this->enableTwoFactor($user);

        $this->withToken($this->challengeTokenFor($user, portal: true))
            ->postJson('/api/v1/auth/2fa/verify', ['code' => '000000'])
            ->assertStatus(401);
    }
}
