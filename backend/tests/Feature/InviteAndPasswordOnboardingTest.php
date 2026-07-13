<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Mail\EmployeeInvitation;
use App\Mail\UserInvitation;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\InvitationService;
use Database\Seeders\LookupSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * FAZ A2 — Davet kabul + anlık şifre + portal erişim.
 */
class InviteAndPasswordOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create([
            'status' => CompanyStatus::Active,
        ]);
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'password' => Hash::make('Password1!'),
        ]);
        $this->assignSpatieAdminRole($this->admin);
        $this->admin = $this->admin->fresh();
    }

    public function test_invite_sends_mail_and_accept_activates_user(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->admin);

        $adminRoleId = Role::findByName('admin', 'sanctum')->id;

        $this->postJson('/api/v1/users/invite', [
            'email' => 'davetli@test.local',
            'name' => 'Davetli Kullanıcı',
            'roles' => [$adminRoleId],
        ])->assertOk();

        Mail::assertQueued(UserInvitation::class);

        $user = User::where('email', 'davetli@test.local')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->invitation_token);

        $plain = null;
        Mail::assertQueued(UserInvitation::class, function (UserInvitation $mail) use (&$plain) {
            $plain = $mail->invitationToken;

            return true;
        });
        $this->assertIsString($plain);
        $this->assertNotSame('', $plain);

        $this->postJson('/api/v1/auth/accept-invitation', [
            'token' => $plain,
            'email' => 'davetli@test.local',
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->invitation_token);
        $this->assertNotNull($user->invitation_accepted_at);
        $this->assertTrue(Hash::check('NewPass1!', $user->password));

        $this->postJson('/api/v1/auth/login', [
            'email' => 'davetli@test.local',
            'password' => 'NewPass1!',
        ])->assertOk();
    }

    public function test_invitation_token_is_single_use_and_expires(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/users/invite', [
            'email' => 'tek@test.local',
            'name' => 'Tek Kullanım',
        ])->assertOk();

        $plain = null;
        Mail::assertQueued(UserInvitation::class, function (UserInvitation $mail) use (&$plain) {
            $plain = $mail->invitationToken;

            return true;
        });

        $payload = [
            'token' => $plain,
            'email' => 'tek@test.local',
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ];

        $this->postJson('/api/v1/auth/accept-invitation', $payload)->assertOk();
        $this->postJson('/api/v1/auth/accept-invitation', $payload)->assertStatus(422);

        Mail::fake();
        $this->postJson('/api/v1/users/invite', [
            'email' => 'expired@test.local',
            'name' => 'Süresi Dolan',
        ])->assertOk();

        $expiredPlain = null;
        Mail::assertQueued(UserInvitation::class, function (UserInvitation $mail) use (&$expiredPlain) {
            $expiredPlain = $mail->invitationToken;

            return true;
        });

        $expiredUser = User::where('email', 'expired@test.local')->firstOrFail();
        $expiredUser->forceFill([
            'invited_at' => now()->subDays(InvitationService::EXPIRE_DAYS + 1),
        ])->save();

        $this->postJson('/api/v1/auth/accept-invitation', [
            'token' => $expiredPlain,
            'email' => 'expired@test.local',
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertStatus(422);
    }

    public function test_immediate_password_sets_must_change_and_login_works(): void
    {
        Sanctum::actingAs($this->admin);

        $adminRoleId = Role::findByName('admin', 'sanctum')->id;

        $this->postJson('/api/v1/users', [
            'name' => 'Anlık Şifre',
            'email' => 'anlik@test.local',
            'password' => 'TempPass1!',
            'roles' => [$adminRoleId],
        ])->assertCreated();

        $user = User::where('email', 'anlik@test.local')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->is_active);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'anlik@test.local',
            'password' => 'TempPass1!',
        ])->assertOk();

        $this->assertTrue($login->json('data.user.must_change_password'));

        Sanctum::actingAs($user);
        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'TempPass1!',
            'password' => 'Changed1!',
            'password_confirmation' => 'Changed1!',
        ])->assertOk();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
    }

    public function test_employee_portal_invite_and_set_password_modes(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-INV-1',
            'name' => 'Portal Davet',
            'status' => 'active',
            'create_portal_access' => true,
            'portal_email' => 'portal.invite@test.local',
            'portal_access_mode' => 'invite',
        ])->assertCreated();

        Mail::assertQueued(EmployeeInvitation::class);
        $invited = User::where('email', 'portal.invite@test.local')->firstOrFail();
        $this->assertFalse($invited->is_active);
        $this->assertNotNull($invited->invitation_token);
        $this->assertTrue($invited->hasRole('employee'));
        $this->assertFalse(\App\Support\PanelAccess::has($invited));

        $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-PWD-1',
            'name' => 'Portal Şifre',
            'status' => 'active',
            'create_portal_access' => true,
            'portal_email' => 'portal.pwd@test.local',
            'portal_access_mode' => 'set_password',
            'portal_password' => 'PortalPass1!',
        ])->assertCreated();

        $pwdUser = User::where('email', 'portal.pwd@test.local')->firstOrFail();
        $this->assertTrue($pwdUser->is_active);
        $this->assertTrue($pwdUser->must_change_password);
        $this->assertNull($pwdUser->invitation_token);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'portal.pwd@test.local',
            'password' => 'PortalPass1!',
            'portal_login' => true,
        ])->assertOk();
    }

    public function test_invite_requires_permission_and_is_tenant_isolated(): void
    {
        Mail::fake();

        $other = Company::factory()->create(['status' => CompanyStatus::Active]);
        $otherAdmin = User::factory()->create([
            'company_id' => $other->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);

        $noPerm = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $noPerm->assignRole('employee');
        Employee::factory()->forUser($noPerm)->create();
        Sanctum::actingAs($noPerm->fresh());
        $this->postJson('/api/v1/users/invite', [
            'email' => 'x@test.local',
            'name' => 'X',
        ])->assertForbidden();

        Sanctum::actingAs($otherAdmin->fresh());
        $this->postJson('/api/v1/users/invite', [
            'email' => 'other.invite@test.local',
            'name' => 'Other',
        ])->assertOk();

        $u = User::where('email', 'other.invite@test.local')->firstOrFail();
        $this->assertSame($other->id, $u->company_id);
        $this->assertNotSame($this->company->id, $u->company_id);
    }

    public function test_forgot_password_flow_still_works(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'forgot@test.local',
            'password' => Hash::make('OldPass1!'),
            'is_active' => true,
            'type' => UserType::User,
        ]);

        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'forgot@test.local',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }
}
