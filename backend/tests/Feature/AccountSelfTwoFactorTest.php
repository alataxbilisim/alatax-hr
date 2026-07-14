<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Faz 4 — self-service 2FA (/auth/2fa/*) + preferences.density.
 */
class AccountSelfTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->google2fa = new Google2FA;

        foreach ([
            'management.users.view',
            'management.users.edit',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);

        RateLimiter::clear(md5('auth'.request()->ip()));
        RateLimiter::clear(md5('auth'.''));
    }

    private function regularUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'password' => Hash::make('Password1!'),
            'is_active' => true,
            'two_factor_enabled' => false,
            'preferences' => ['theme' => 'dark', 'locale' => 'tr'],
        ], $attrs));
    }

    private function enableSelfFully(User $user): array
    {
        Sanctum::actingAs($user);

        $enable = $this->postJson('/api/v1/auth/2fa/enable')->assertOk();
        $secret = $enable->json('data.secret');
        $code = $this->google2fa->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])->assertOk();
        $user->refresh();

        return [
            'secret' => $secret,
            'recovery' => $enable->json('data.recovery_codes'),
        ];
    }

    public function test_user_without_users_edit_can_enable_confirm_disable_own_2fa(): void
    {
        $user = $this->regularUser();
        $this->assertFalse($user->can('management.users.edit'));

        Sanctum::actingAs($user);

        $enable = $this->postJson('/api/v1/auth/2fa/enable')->assertOk();
        $secret = $enable->json('data.secret');
        $this->assertFalse($enable->json('data.two_factor_enabled'));
        $this->assertCount(8, $enable->json('data.recovery_codes'));

        $code = $this->google2fa->getCurrentOtp($secret);
        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);

        $this->assertTrue($user->fresh()->two_factor_enabled);

        $this->getJson('/api/v1/auth/2fa/status')
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);

        $this->postJson('/api/v1/auth/2fa/disable', ['password' => 'Password1!'])
            ->assertOk();

        $this->assertFalse($user->fresh()->two_factor_enabled);
    }

    public function test_user_without_permission_cannot_hit_admin_2fa_routes(): void
    {
        $actor = $this->regularUser();
        $other = $this->regularUser(['email' => 'other@example.com']);

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/users/{$other->id}/2fa/enable")
            ->assertStatus(403);

        $this->postJson("/api/v1/users/{$other->id}/2fa/verify", ['code' => '123456'])
            ->assertStatus(403);

        $this->postJson("/api/v1/users/{$other->id}/2fa/disable", ['password' => 'Password1!'])
            ->assertStatus(403);
    }

    public function test_preferences_density_persists_via_profile(): void
    {
        $user = $this->regularUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'preferences' => ['density' => 'compact'],
        ])
            ->assertOk()
            ->assertJsonPath('data.user.preferences.density', 'compact');

        $this->assertSame('compact', $user->fresh()->preferences['density'] ?? null);

        $this->putJson('/api/v1/auth/profile', [
            'preferences' => ['density' => 'comfortable'],
        ])
            ->assertOk()
            ->assertJsonPath('data.user.preferences.density', 'comfortable');
    }

    public function test_self_regenerate_recovery_codes_with_password(): void
    {
        $user = $this->regularUser();
        $this->enableSelfFully($user);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/auth/2fa/recovery-codes/regenerate', [
            'password' => 'Password1!',
        ])->assertOk();

        $this->assertCount(8, $res->json('data.recovery_codes'));

        $this->getJson('/api/v1/auth/2fa/recovery-codes')
            ->assertOk()
            ->assertJsonPath('data.remaining_count', 8);
    }

    public function test_invalid_density_rejected(): void
    {
        $user = $this->regularUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'preferences' => ['density' => 'huge'],
        ])->assertStatus(422);
    }
}
