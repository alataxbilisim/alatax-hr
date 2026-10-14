<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payslip;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPortalEmployees;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

/**
 * K2 — `private` disk gerçek config ile (Storage::fake yok): yükle → indir → sil.
 */
class PrivateDiskFileFlowTest extends TestCase
{
    use CreatesPortalEmployees;
    use CreatesUsersWithPermissions;
    use RefreshDatabase;
    use SetsCompanyContext;

    private Company $company;

    private User $admin;

    /** @var list<string> */
    private array $createdPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(LookupSeeder::class);

        $this->company = $this->createActiveCompany();
        $this->admin = $this->companyAdminFor($this->company);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            if (Storage::disk('private')->exists($path)) {
                Storage::disk('private')->delete($path);
            }
        }

        parent::tearDown();
    }

    public function test_private_disk_is_configured_as_non_public_local_disk(): void
    {
        $disk = config('filesystems.disks.private');

        $this->assertSame('local', $disk['driver']);
        $this->assertSame(storage_path('app/private'), $disk['root']);
        $this->assertSame('private', $disk['visibility']);
        $this->assertFalse($disk['serve']);
        $this->assertTrue($disk['throw']);
    }

    public function test_employee_document_upload_download_delete_on_real_disk(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->actingAsInCompany($this->admin, $this->company);

        $upload = $this->post("/api/v1/employees/{$employee->id}/documents", [
            'file' => UploadedFile::fake()->create('sozlesme.pdf', 12, 'application/pdf'),
            'title' => 'İş sözleşmesi',
            'category' => 'contract',
        ], ['Accept' => 'application/json'])->assertCreated();

        $path = (string) $upload->json('data.file_path');
        $this->createdPaths[] = $path;
        $this->assertStringStartsWith("employees/{$employee->id}/documents/", $path);
        $this->assertFileExists(storage_path('app/private/'.$path));
        $this->assertFileDoesNotExist(storage_path('app/public/'.$path));

        $documentId = $upload->json('data.id');

        $download = $this->get("/api/v1/employees/{$employee->id}/documents/{$documentId}/download")->assertOk();
        $this->assertStringContainsString('sozlesme.pdf', (string) $download->headers->get('content-disposition'));

        $this->deleteJson("/api/v1/employees/{$employee->id}/documents/{$documentId}")->assertOk();
        $this->assertFileDoesNotExist(storage_path('app/private/'.$path));
    }

    public function test_payslip_upload_and_portal_download_on_real_disk(): void
    {
        $portalUser = $this->portalEmployee($this->company);
        $employee = $portalUser->employee;

        $this->actingAsInCompany($this->admin, $this->company);
        $fileName = 'bordro-'.Str::lower(Str::random(10)).'.pdf';

        $store = $this->post('/api/v1/payslips', [
            'employee_id' => $employee->id,
            'period' => '2026-09',
            'net_salary' => 1000,
            'publish' => 1,
            'file' => UploadedFile::fake()->create($fileName, 8, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $payslip = Payslip::query()->findOrFail($store->json('data.id'));
        $this->createdPaths[] = (string) $payslip->file_path;
        $this->assertTrue($payslip->is_published);
        $this->assertFileExists(storage_path('app/private/'.$payslip->file_path));

        auth()->forgetGuards();
        $this->withToken($portalUser->createToken('portal-token', ['portal:access'])->plainTextToken)
            ->get("/api/v1/portal/payslips/{$payslip->id}/download")
            ->assertOk();

        auth()->forgetGuards();
        $this->actingAsInCompany($this->admin, $this->company);
        $this->deleteJson("/api/v1/payslips/{$payslip->id}")->assertOk();
        $this->assertFileDoesNotExist(storage_path('app/private/'.$payslip->file_path));
    }
}
