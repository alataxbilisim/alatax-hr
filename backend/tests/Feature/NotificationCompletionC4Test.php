<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Mail\NotificationMail;
use App\Models\ApprovalRecord;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\DefaultLeaveApprovalWorkflowService;
use App\Services\Notification\NotificationService;
use App\Services\WorkflowService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ 4C-2 — olay tamamlama, tercihler, şablonlar.
 */
class NotificationCompletionC4Test extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->enableModule($this->company, 'leave-management');
        $this->enableModule($this->company, 'expense-management');
        $this->enableModule($this->company, 'asset-management');

        $this->leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual',
            'code' => 'AN-4C2',
            'is_active' => true,
            'default_days' => 14,
        ]);

        app(DefaultLeaveApprovalWorkflowService::class)->ensureForCompany($this->company);
    }

    private function enableModule(Company $company, string $slug): void
    {
        $module = Module::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'is_core' => false, 'is_active' => true]
        );
        $company->modules()->syncWithoutDetaching([
            $module->id => ['is_active' => true, 'activated_at' => now()],
        ]);
    }

    /**
     * @return array{requester: User, manager: User, leave: LeaveRequest, record: ApprovalRecord}
     */
    private function startLeaveWorkflow(): array
    {
        $manager = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'mgr4c2@example.com',
        ]);
        $manager->assignRole('manager');

        $managerEmp = Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $manager->id,
            'employee_code' => 'M-4C2',
            'status' => 'active',
        ]);

        $requester = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'req4c2@example.com',
        ]);
        $requester->assignRole('employee');

        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'employee_code' => 'E-4C2',
            'status' => 'active',
            'manager_id' => $managerEmp->id,
        ]);

        LeaveBalance::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => (int) now()->year,
            'total_days' => 20,
            'used_days' => 0,
            'pending_days' => 0,
            'remaining_days' => 20,
        ]);

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'total_days' => 2,
            'reason' => '4C-2 test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        Mail::fake();

        $record = app(WorkflowService::class)->startWorkflow($leave, ['total_days' => 2]);
        $this->assertNotNull($record);

        return compact('requester', 'manager', 'leave', 'record');
    }

    public function test_leave_approve_and_reject_notify_requester(): void
    {
        $ctx = $this->startLeaveWorkflow();
        Sanctum::actingAs($ctx['manager']);
        app(WorkflowService::class)->approve($ctx['record']->fresh(), $ctx['manager']->id, 'ok');

        $this->assertTrue(
            $ctx['requester']->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'leave.approved'
            )
        );

        // Red yolu ayrı talep
        $leave2 = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $ctx['requester']->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(21)->toDateString(),
            'total_days' => 2,
            'reason' => 'reject path',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        Mail::fake();
        $record2 = app(WorkflowService::class)->startWorkflow($leave2, ['total_days' => 2]);
        app(WorkflowService::class)->reject($record2->fresh(), $ctx['manager']->id, 'yetersiz');

        $this->assertTrue(
            $ctx['requester']->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'leave.rejected'
            )
        );
    }

    public function test_expense_approve_notifies_requester(): void
    {
        $requester = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'exp-req@example.com',
        ]);

        $claim = ExpenseClaim::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'title' => 'Taxi',
            'claim_number' => 'EXP-4C2-001',
            'total_amount' => 100,
            'currency' => 'TRY',
            'expense_date' => now()->toDateString(),
            'status' => ExpenseClaim::STATUS_SUBMITTED,
        ]);

        Mail::fake();
        app(NotificationService::class)->notifyWorkflowOutcome($claim, 'approved');
        app(NotificationService::class)->notifyWorkflowOutcome($claim, 'rejected', 'uygun değil');

        $events = $requester->fresh()->notifications()->get()->pluck('data.event');
        $this->assertTrue($events->contains('expense.approved'));
        $this->assertTrue($events->contains('expense.rejected'));
    }

    public function test_in_app_preference_off_skips_notification(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'pref-off@example.com',
            'preferences' => [
                'notifications' => [
                    'in_app' => ['approvals' => false],
                    'email' => ['approvals' => false],
                ],
            ],
        ]);

        Mail::fake();
        app(NotificationService::class)->notify($user, 'approval.requested', [
            'company_id' => $this->company->id,
            'entity' => 'İzin',
            'step' => 'Yönetici',
        ]);

        $this->assertSame(0, $user->notifications()->count());
        Mail::assertNothingQueued();
    }

    public function test_security_event_ignores_preferences(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'sec@example.com',
            'preferences' => [
                'notifications' => [
                    'in_app' => ['approvals' => false, 'requests' => false],
                    'email' => ['approvals' => false, 'requests' => false],
                ],
            ],
        ]);

        Mail::fake();
        app(NotificationService::class)->notifySecurity($user, 'security.password_changed');

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('security.password_changed', $user->notifications()->first()->data['event'] ?? null);
        Mail::assertQueued(NotificationMail::class);
    }

    public function test_reminder_email_default_off(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'rem@example.com',
        ]);

        Mail::fake();
        app(NotificationService::class)->notify($user, 'approval.reminder', [
            'company_id' => $this->company->id,
            'entity' => 'İzin',
            'step' => 'Yönetici',
            'days' => '3',
        ]);

        $this->assertSame(1, $user->notifications()->count());
        Mail::assertNothingQueued();
    }

    public function test_template_override_renders_and_escapes(): void
    {
        NotificationTemplate::create([
            'company_id' => $this->company->id,
            'event_key' => 'leave.approved',
            'subject' => 'Özel: {{user}}',
            'body' => 'Merhaba {{user}} — tamam',
        ]);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'name' => '<script>x</script>',
            'email' => 'tpl@example.com',
        ]);

        Mail::fake();
        app(NotificationService::class)->notify($user, 'leave.approved', [
            'company_id' => $this->company->id,
            'user' => $user->name,
            'date' => '2026-07-16',
        ]);

        $notif = $user->notifications()->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('&lt;script&gt;', $notif->data['title'] ?? '');
        $this->assertStringNotContainsString('<script>', $notif->data['title'] ?? '');
        $this->assertStringContainsString('&lt;script&gt;', $notif->data['message'] ?? '');
    }

    public function test_template_tenant_isolation(): void
    {
        NotificationTemplate::create([
            'company_id' => $this->company->id,
            'event_key' => 'leave.approved',
            'subject' => 'A Firması',
            'body' => 'A gövde',
        ]);

        $foreign = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::User,
            'email' => 'foreign-tpl@example.com',
        ]);

        Mail::fake();
        app(NotificationService::class)->notify($foreign, 'leave.approved', [
            'company_id' => $this->otherCompany->id,
            'date' => '2026-07-16',
        ]);

        $notif = $foreign->notifications()->first();
        $this->assertNotNull($notif);
        $this->assertStringNotContainsString('A Firması', $notif->data['title'] ?? '');
    }

    public function test_template_api_auth_and_permission(): void
    {
        $this->getJson('/api/v1/notification-templates')->assertUnauthorized();

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        $role = Role::findOrCreate('viewer_no_notif_c4', 'sanctum');
        $role->forceFill(['data_scope' => 'company'])->save();
        $role->syncPermissions(['leaves.requests.view']);
        $user->assignRole($role);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/notification-templates')->assertForbidden();

        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($admin);
        Sanctum::actingAs($admin->fresh());

        $list = $this->getJson('/api/v1/notification-templates')->assertOk();
        $this->assertNotEmpty($list->json('data.templates'));

        $this->putJson('/api/v1/notification-templates/leave.approved', [
            'subject' => 'Firma başlık {{user}}',
            'body' => 'Firma gövde {{date}}',
        ])->assertOk();

        $this->assertDatabaseHas('notification_templates', [
            'company_id' => $this->company->id,
            'event_key' => 'leave.approved',
        ]);

        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($otherAdmin);
        Sanctum::actingAs($otherAdmin->fresh());
        $otherList = $this->getJson('/api/v1/notification-templates')->assertOk();
        $leaveRow = collect($otherList->json('data.templates'))
            ->firstWhere('event_key', 'leave.approved');
        $this->assertNull($leaveRow['override'] ?? null);
    }

    public function test_asset_assign_notifies_user(): void
    {
        $assignee = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'asset-user@example.com',
        ]);

        $category = AssetCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Laptop',
            'is_active' => true,
        ]);

        $asset = Asset::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'name' => 'MacBook',
            'asset_code' => 'AST-4C2',
            'status' => 'available',
            'condition' => 'good',
        ]);

        Mail::fake();
        app(NotificationService::class)->notifyAssetAssigned($asset, $assignee);

        $this->assertTrue(
            $assignee->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'asset.assigned'
            )
        );
    }
}
