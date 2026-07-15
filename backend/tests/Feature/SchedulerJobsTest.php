<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\DocumentExpiryAlert;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\Documents\DocumentExpiryAlertService;
use App\Services\LeaveCalculationService;
use App\Services\Leaves\LeaveAccrualBatchService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Scheduler temeli — hakediş batch + süreli evrak uyarıları.
 */
class SchedulerJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['admin', 'hr_manager', 'employee'] as $role) {
            Role::findOrCreate($role, 'sanctum');
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_monthly_accrual_isolates_company_failures(): void
    {
        $ok = Company::factory()->create(['status' => CompanyStatus::Active, 'name' => 'OK Co']);
        $bad = Company::factory()->create(['status' => CompanyStatus::Active, 'name' => 'Bad Co']);
        Company::factory()->create(['status' => CompanyStatus::Suspended, 'name' => 'Skip']);

        $calc = Mockery::mock(LeaveCalculationService::class);
        $calc->shouldReceive('processMonthlyAccruals')
            ->andReturnUsing(function (int $companyId) use ($bad): array {
                if ($companyId === $bad->id) {
                    throw new \RuntimeException('simulated failure');
                }

                return [['user_id' => 1, 'accrued' => 1.0]];
            });
        $this->app->instance(LeaveCalculationService::class, $calc);

        $summary = app(LeaveAccrualBatchService::class)->processMonthlyForAllActiveCompanies(7, 2026);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['failed']);
        $this->assertCount(2, $summary['companies']);

        $byId = collect($summary['companies'])->keyBy('company_id');
        $this->assertSame('ok', $byId[$ok->id]['status']);
        $this->assertSame('failed', $byId[$bad->id]['status']);
    }

    public function test_document_expiry_notifies_once_and_is_tenant_isolated(): void
    {
        Mail::fake();
        $today = Carbon::parse('2026-07-15');

        $companyA = Company::factory()->create(['status' => CompanyStatus::Active]);
        $companyB = Company::factory()->create(['status' => CompanyStatus::Active]);

        $hrA = User::factory()->create([
            'company_id' => $companyA->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
            'email' => 'hr-a@example.com',
        ]);
        $hrA->assignRole('hr_manager');

        $empUserA = User::factory()->create([
            'company_id' => $companyA->id,
            'type' => UserType::User,
            'is_active' => true,
            'email' => 'emp-a@example.com',
        ]);
        $empA = Employee::factory()->forUser($empUserA)->create(['company_id' => $companyA->id]);

        $docA = EmployeeDocument::create([
            'company_id' => $companyA->id,
            'employee_id' => $empA->id,
            'title' => 'Sözleşme A',
            'category' => 'contract',
            'file_path' => 'x/a.pdf',
            'file_name' => 'a.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 100,
            'expiry_date' => $today->copy()->addDays(30)->toDateString(),
            'is_visible_to_employee' => true,
            'status' => 'active',
            'uploaded_by' => $hrA->id,
        ]);

        $hrB = User::factory()->create([
            'company_id' => $companyB->id,
            'type' => UserType::CompanyAdmin,
            'is_active' => true,
        ]);
        $hrB->assignRole('hr_manager');
        $empUserB = User::factory()->create([
            'company_id' => $companyB->id,
            'type' => UserType::User,
            'is_active' => true,
        ]);
        $empB = Employee::factory()->forUser($empUserB)->create(['company_id' => $companyB->id]);
        EmployeeDocument::create([
            'company_id' => $companyB->id,
            'employee_id' => $empB->id,
            'title' => 'Sözleşme B',
            'category' => 'contract',
            'file_path' => 'x/b.pdf',
            'file_name' => 'b.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 100,
            'expiry_date' => $today->copy()->addDays(30)->toDateString(),
            'is_visible_to_employee' => true,
            'status' => 'active',
            'uploaded_by' => $hrB->id,
        ]);

        $service = app(DocumentExpiryAlertService::class);

        $first = $service->processCompany((int) $companyA->id, $today);
        $this->assertSame(1, $first['notified']);
        $this->assertSame(0, $first['skipped']);

        $this->assertTrue(
            $empUserA->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'document.expiring'
            )
        );
        $this->assertTrue(
            $hrA->fresh()->notifications()->get()->contains(
                fn ($n) => ($n->data['event'] ?? null) === 'document.expiring'
            )
        );
        $this->assertSame(0, $hrB->fresh()->notifications()->count());
        $this->assertDatabaseHas('document_expiry_alerts', [
            'employee_document_id' => $docA->id,
            'threshold_days' => 30,
            'company_id' => $companyA->id,
        ]);

        $second = $service->processCompany((int) $companyA->id, $today);
        $this->assertSame(0, $second['notified']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, DocumentExpiryAlert::query()->where('employee_document_id', $docA->id)->count());

        // İkinci koşu ekstra bildirim üretmez (HR + personel = 2)
        $this->assertSame(1, $hrA->fresh()->notifications()->count());
        $this->assertSame(1, $empUserA->fresh()->notifications()->count());
    }
}
