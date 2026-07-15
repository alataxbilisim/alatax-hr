<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Mail\NotificationMail;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\User;
use App\Services\DefaultLeaveApprovalWorkflowService;
use App\Services\Notification\NotificationService;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * FAZ 4C-1 — Bildirim Merkezi çekirdeği.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'leaves.requests.view',
            'leaves.requests.create',
            'leaves.requests.approve',
            'leaves.requests.edit',
            'approvals.view',
            'approvals.approve',
        ] as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        foreach (['admin', 'hr_manager', 'manager', 'employee'] as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->otherCompany = Company::factory()->create(['status' => CompanyStatus::Active]);

        $this->enableModule($this->company, 'leave-management');
        $this->enableModule($this->otherCompany, 'leave-management');

        $this->leaveType = LeaveType::create([
            'company_id' => $this->company->id,
            'name' => 'Annual',
            'code' => 'AN-4C1',
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
            'email' => 'mgr4c1@example.com',
        ]);
        $manager->assignRole('manager');
        $manager->givePermissionTo(['leaves.requests.approve', 'approvals.view', 'approvals.approve']);

        $managerEmp = Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $manager->id,
            'employee_code' => 'M-4C1',
            'status' => 'active',
        ]);

        $requester = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'req4c1@example.com',
        ]);
        $requester->assignRole('employee');

        Employee::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'employee_code' => 'E-4C1',
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
            'reason' => '4C-1 test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        Mail::fake();

        $record = app(WorkflowService::class)->startWorkflow($leave, ['total_days' => 2]);
        $this->assertNotNull($record);

        return compact('requester', 'manager', 'leave', 'record');
    }

    public function test_unauthenticated_notifications_return_401(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_leave_workflow_notifies_approver_in_app_and_queues_mail(): void
    {
        $ctx = $this->startLeaveWorkflow();
        $manager = $ctx['manager'];

        $this->assertSame(1, $manager->notifications()->count());
        $notif = $manager->notifications()->first();
        $this->assertSame('approval.requested', $notif->data['event'] ?? null);
        $this->assertSame($this->company->id, (int) $notif->company_id);

        Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($manager) {
            return $mail->hasTo($manager->email);
        });
    }

    public function test_approve_notifies_requester(): void
    {
        $ctx = $this->startLeaveWorkflow();
        $manager = $ctx['manager'];
        $requester = $ctx['requester'];
        $record = $ctx['record'];

        // Tek adımlı varsayılan workflow → onay = tamamlanır
        Sanctum::actingAs($manager);
        $ok = app(WorkflowService::class)->approve($record->fresh(), $manager->id, 'ok');
        $this->assertTrue($ok);

        $this->assertTrue(
            $requester->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'leave.approved'
            )
        );

        Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($requester) {
            return $mail->hasTo($requester->email);
        });
    }

    public function test_reject_notifies_requester(): void
    {
        $ctx = $this->startLeaveWorkflow();
        $manager = $ctx['manager'];
        $requester = $ctx['requester'];
        $record = $ctx['record'];

        Sanctum::actingAs($manager);
        $ok = app(WorkflowService::class)->reject($record->fresh(), $manager->id, 'yetersiz bakiye');
        $this->assertTrue($ok);

        $this->assertTrue(
            $requester->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'leave.rejected'
            )
        );
    }

    public function test_delegation_notifies_delegate(): void
    {
        $ctx = $this->startLeaveWorkflow();
        // Yeniden başlat: vekalet ile
        $manager = $ctx['manager'];
        $requester = $ctx['requester'];

        $delegate = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'delegate4c1@example.com',
        ]);
        $delegate->assignRole('manager');
        $delegate->givePermissionTo(['leaves.requests.approve', 'approvals.view', 'approvals.approve']);

        ApprovalDelegation::create([
            'company_id' => $this->company->id,
            'delegator_id' => $manager->id,
            'delegate_id' => $delegate->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'is_active' => true,
            'entity_type' => null,
        ]);

        // Mevcut kaydı temizle / yeni talep
        $leave2 = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $requester->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->addDays(14)->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(),
            'total_days' => 2,
            'reason' => 'delegation',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        Mail::fake();
        $record = app(WorkflowService::class)->startWorkflow($leave2, ['total_days' => 2]);
        $this->assertNotNull($record);
        $this->assertSame($delegate->id, $record->approver_id);

        $this->assertGreaterThanOrEqual(1, $delegate->notifications()->count());
        // Asıl onaycı (vekalet veren) de bilgilendirilir
        $this->assertGreaterThanOrEqual(1, $manager->fresh()->notifications()->count());
    }

    public function test_email_preference_off_skips_mail_keeps_in_app(): void
    {
        $manager = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
            'email' => 'pref4c1@example.com',
            'preferences' => [
                'notifications' => [
                    'email' => [
                        'approvals' => false,
                        'requests' => true,
                        'tasks' => true,
                    ],
                ],
            ],
        ]);

        Mail::fake();
        app(NotificationService::class)->notify($manager, 'approval.requested', [
            'company_id' => $this->company->id,
            'entity' => 'İzin',
            'step' => 'Yönetici',
            'date' => now()->toDateString(),
        ]);

        $this->assertSame(1, $manager->notifications()->count());
        Mail::assertNothingQueued();
    }

    public function test_tenant_mismatch_does_not_notify(): void
    {
        $foreign = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::User,
        ]);

        Mail::fake();
        app(NotificationService::class)->notify($foreign, 'approval.requested', [
            'company_id' => $this->company->id,
            'entity' => 'İzin',
            'step' => 'X',
        ]);

        $this->assertSame(0, $foreign->notifications()->count());
        Mail::assertNothingQueued();
    }

    public function test_mark_read_and_unread_count(): void
    {
        $ctx = $this->startLeaveWorkflow();
        $manager = $ctx['manager'];

        Sanctum::actingAs($manager);
        $list = $this->getJson('/api/v1/notifications')->assertOk();
        $this->assertGreaterThanOrEqual(1, $list->json('data.unread_count'));
        $id = $list->json('data.notifications.0.id');
        $this->assertNotEmpty($id);

        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();
        $after = $this->getJson('/api/v1/notifications')->assertOk();
        $this->assertSame(0, $after->json('data.unread_count'));

        // Yeni bildirim + tümünü okundu
        app(NotificationService::class)->notify($manager->fresh(), 'approval.requested', [
            'company_id' => $this->company->id,
            'entity' => 'İzin',
            'step' => 'Y',
        ]);
        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $final = $this->getJson('/api/v1/notifications')->assertOk();
        $this->assertSame(0, $final->json('data.unread_count'));
    }

    public function test_other_tenant_cannot_see_notification(): void
    {
        $ctx = $this->startLeaveWorkflow();
        $manager = $ctx['manager'];
        $notifId = $manager->notifications()->first()->id;

        $otherAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'type' => UserType::CompanyAdmin,
        ]);
        Sanctum::actingAs($otherAdmin);

        $this->postJson("/api/v1/notifications/{$notifId}/read")->assertNotFound();
        $list = $this->getJson('/api/v1/notifications')->assertOk();
        $ids = collect($list->json('data.notifications'))->pluck('id');
        $this->assertFalse($ids->contains($notifId));
    }
}
