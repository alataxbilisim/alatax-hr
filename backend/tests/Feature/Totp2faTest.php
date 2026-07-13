<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Faz 2 — gerçek TOTP 2FA (stub kaldırıldı).
 */
class Totp2faTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private TwoFactorService $twoFactor;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->twoFactor = app(TwoFactorService::class);
        $this->google2fa = new Google2FA;

        foreach ([
            'management.users.view',
            'management.users.edit',
            'management.users.create',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);

        RateLimiter::clear(md5('auth'.request()->ip()));
        RateLimiter::clear(md5('auth'.''));
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'password' => Hash::make('Password1!'),
            'is_active' => true,
        ]);
        $user->givePermissionTo(['management.users.view', 'management.users.edit']);

        return $user;
    }

    private function regularUser(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'password' => Hash::make('Password1!'),
            'is_active' => true,
            'two_factor_enabled' => false,
        ], $attrs));

        // Company paneli login: panel erişimi gerekir (yalnızca portal-self yetmez)
        $user->givePermissionTo('management.users.view');

        return $user;
    }

    private function enableFully(User $target, User $admin): array
    {
        Sanctum::actingAs($admin);

        $enable = $this->postJson("/api/v1/users/{$target->id}/2fa/enable")
            ->assertOk();

        $secret = $enable->json('data.secret');
        $recovery = $enable->json('data.recovery_codes');
        $code = $this->google2fa->getCurrentOtp($secret);

        $this->postJson("/api/v1/users/{$target->id}/2fa/verify", ['code' => $code])
            ->assertOk();

        $target->refresh();

        // Sonraki login/challenge isteklerinde actingAs kalmasın
        auth()->forgetGuards();

        return compact('secret', 'recovery');
    }

    public function test_enable_produces_base32_secret_qr_and_recovery_codes(): void
    {
        $admin = $this->admin();
        $target = $this->regularUser();
        Sanctum::actingAs($admin);

        $res = $this->postJson("/api/v1/users/{$target->id}/2fa/enable")->assertOk();

        $secret = $res->json('data.secret');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertGreaterThanOrEqual(16, strlen($secret));
        $this->assertStringStartsWith('otpauth://totp/', $res->json('data.qr_code_url'));
        $this->assertStringContainsString('<svg', $res->json('data.qr_code_svg'));
        $this->assertCount(8, $res->json('data.recovery_codes'));
        $this->assertFalse($res->json('data.two_factor_enabled'));

        $target->refresh();
        $this->assertFalse($target->two_factor_enabled);
        $this->assertNotNull($target->two_factor_secret);

        // Recovery DB'de hash (düz metin değil)
        $stored = decrypt($target->two_factor_recovery_codes);
        $this->assertStringNotContainsString($res->json('data.recovery_codes.0'), $stored);
    }

    public function test_wrong_totp_on_enable_verify_fails_and_stays_disabled(): void
    {
        $admin = $this->admin();
        $target = $this->regularUser();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/users/{$target->id}/2fa/enable")->assertOk();

        $this->postJson("/api/v1/users/{$target->id}/2fa/verify", ['code' => '000000'])
            ->assertStatus(400);

        $this->assertFalse($target->fresh()->two_factor_enabled);
        $this->assertSame(1, ActivityLog::where('action', 'two_factor_failed')->count());
    }

    public function test_correct_totp_enables_two_factor(): void
    {
        $admin = $this->admin();
        $target = $this->regularUser();
        $this->enableFully($target, $admin);

        $this->assertTrue($target->fresh()->two_factor_enabled);
        $this->assertSame(1, ActivityLog::where('action', 'two_factor_enable')->count());
    }

    public function test_login_without_2fa_returns_token(): void
    {
        $user = $this->regularUser(['email' => 'plain@example.com']);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'plain@example.com',
            'password' => 'Password1!',
        ])->assertOk();

        $this->assertNotEmpty($res->json('data.token'));
        $this->assertNull($res->json('data.requires_2fa'));
    }

    public function test_login_with_2fa_returns_challenge_not_token(): void
    {
        $admin = $this->admin();
        $user = $this->regularUser(['email' => 'mfa@example.com']);
        $this->enableFully($user, $admin);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'mfa@example.com',
            'password' => 'Password1!',
        ])->assertOk();

        $this->assertTrue($res->json('data.requires_2fa'));
        $this->assertNotEmpty($res->json('data.challenge_token'));
        $this->assertNull($res->json('data.token'));
    }

    public function test_challenge_token_cannot_access_normal_api(): void
    {
        $admin = $this->admin();
        $user = $this->regularUser(['email' => 'chal@example.com']);
        $this->enableFully($user, $admin);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'chal@example.com',
            'password' => 'Password1!',
        ])->assertOk();

        $challenge = $login->json('data.challenge_token');

        $this->withToken($challenge)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403);
    }

    public function test_challenge_wrong_code_no_token_and_audit(): void
    {
        $admin = $this->admin();
        $user = $this->regularUser(['email' => 'wrong@example.com']);
        $this->enableFully($user, $admin);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'Password1!',
        ])->assertOk();

        $this->withToken($login->json('data.challenge_token'))
            ->postJson('/api/v1/auth/2fa/verify', ['code' => '000000'])
            ->assertStatus(401);

        $this->assertSame(1, ActivityLog::where('action', 'two_factor_failed')
            ->where('model_id', $user->id)
            ->count());
    }

    public function test_challenge_correct_code_issues_auth_token(): void
    {
        $admin = $this->admin();
        $user = $this->regularUser(['email' => 'ok@example.com']);
        $creds = $this->enableFully($user, $admin);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'ok@example.com',
            'password' => 'Password1!',
        ])->assertOk();

        $code = $this->google2fa->getCurrentOtp($creds['secret']);

        $res = $this->withToken($login->json('data.challenge_token'))
            ->postJson('/api/v1/auth/2fa/verify', ['code' => $code])
            ->assertOk();

        $token = $res->json('data.token');
        $this->assertNotEmpty($token);

        $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        $this->assertNotNull($pat);
        $this->assertSame('auth-token', $pat->name);

        // Önceki challenge Bearer auth state test içinde kalmasın
        auth()->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_recovery_code_login_and_single_use(): void
    {
        $admin = $this->admin();
        $user = $this->regularUser(['email' => 'rec@example.com']);
        $creds = $this->enableFully($user, $admin);
        $recovery = $creds['recovery'][0];

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'rec@example.com',
            'password' => 'Password1!',
        ])->assertOk();

        $this->withToken($login->json('data.challenge_token'))
            ->postJson('/api/v1/auth/2fa/verify', ['recovery_code' => $recovery])
            ->assertOk()
            ->assertJsonPath('data.token', fn ($t) => is_string($t) && $t !== '');

        // Aynı recovery tekrar
        $login2 = $this->postJson('/api/v1/auth/login', [
            'email' => 'rec@example.com',
            'password' => 'Password1!',
        ])->assertOk();

        $this->withToken($login2->json('data.challenge_token'))
            ->postJson('/api/v1/auth/2fa/verify', ['recovery_code' => $recovery])
            ->assertStatus(401);
    }

    public function test_2fa_verify_is_throttled(): void
    {
        RateLimiter::clear(md5('auth'.request()->ip()));

        $admin = $this->admin();
        $user = $this->regularUser(['email' => 'thr@example.com']);
        $this->enableFully($user, $admin);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'thr@example.com',
            'password' => 'Password1!',
        ])->assertOk();
        $challenge = $login->json('data.challenge_token');

        $last = null;
        for ($i = 0; $i < 11; $i++) {
            $last = $this->withToken($challenge)
                ->postJson('/api/v1/auth/2fa/verify', ['code' => '000000']);
        }

        $this->assertSame(429, $last->status());
    }

    public function test_disable_requires_code_or_password(): void
    {
        $admin = $this->admin();
        $user = $this->regularUser();
        $this->enableFully($user, $admin);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/users/{$user->id}/2fa/disable")
            ->assertStatus(422);

        $this->postJson("/api/v1/users/{$user->id}/2fa/disable", ['password' => 'Password1!'])
            ->assertOk();

        $this->assertFalse($user->fresh()->two_factor_enabled);
        $this->assertSame(1, ActivityLog::where('action', 'two_factor_disable')->count());
    }
}
