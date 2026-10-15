<?php

namespace Tests\Security;

use App\Enums\JobPositionStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Module;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOtherCompanyFixture;
use Tests\Concerns\CreatesPortalEmployees;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

/**
 * Adım 03B S1 — hassas yüklemeler gerçek `private` diske gider (Storage::fake yok),
 * `/storage/...` ile erişilemez, yalnız yetki kontrollü uçtan indirilir.
 */
class SensitiveFileStorageTest extends TestCase
{
    use CreatesOtherCompanyFixture;
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

        $this->company = $this->createActiveCompany(['slug' => 'hassas-'.Str::lower(Str::random(6))]);
        $this->enableModules($this->company);
        $this->admin = $this->companyAdminFor($this->company);
    }

    private function enableModules(Company $company): void
    {
        foreach (['document-management', 'leave-management', 'job-applications'] as $slug) {
            $module = Module::firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_core' => false, 'is_active' => true]);
            $company->modules()->syncWithoutDetaching([$module->id => ['is_active' => true, 'activated_at' => now()]]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            foreach (['private', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            }
        }

        parent::tearDown();
    }

    public function test_company_document_is_private_and_downloadable_only_with_authorization(): void
    {
        $this->actingAsInCompany($this->admin, $this->company);

        $upload = $this->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('politika.pdf', 10, 'application/pdf'),
            'name' => 'İK politikası',
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = Document::query()->findOrFail($upload->json('data.id'));
        $this->assertPrivateOnly((string) $document->file_path);
        $this->assertStringNotContainsString('/storage/', (string) $upload->json('data.file_path'));

        $this->get("/api/v1/documents/{$document->id}/download")->assertOk();

        $this->actAsOtherCompanyAdmin();
        $this->getJson("/api/v1/documents/{$document->id}/download")->assertNotFound();
        $this->getJson("/api/v1/documents/{$document->id}/versions")->assertNotFound();

        $this->actAsUserWithoutPermissions();
        $this->getJson("/api/v1/documents/{$document->id}/download")->assertForbidden();

        $this->flushSession();
        auth()->forgetGuards();
        $this->flushHeaders();
        $this->getJson("/api/v1/documents/{$document->id}/download")->assertUnauthorized();
    }

    public function test_portal_leave_document_is_private_and_scoped(): void
    {
        $portalUser = $this->portalEmployee($this->company);
        $leaveType = LeaveType::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'requires_document' => true,
        ]);

        $this->actAsPortal($portalUser);
        $store = $this->post('/api/v1/portal/leaves', [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'document' => UploadedFile::fake()->create('saglik-raporu.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $leaveRequest = LeaveRequest::query()->findOrFail($store->json('data.id'));
        $this->assertPrivateOnly((string) $leaveRequest->document_path);
        $this->assertSame('saglik-raporu.pdf', $leaveRequest->document_name);

        $download = $this->get("/api/v1/portal/leaves/{$leaveRequest->id}/document")->assertOk();
        $this->assertStringContainsString('saglik-raporu.pdf', (string) $download->headers->get('content-disposition'));

        $this->actAsPortal($this->portalEmployee($this->company));
        $this->getJson("/api/v1/portal/leaves/{$leaveRequest->id}/document")->assertNotFound();

        $this->actAsAdmin();
        $this->get("/api/v1/leaves/requests/{$leaveRequest->id}/document")->assertOk();

        $this->actAsOtherCompanyAdmin();
        $this->getJson("/api/v1/leaves/requests/{$leaveRequest->id}/document")->assertNotFound();

        $this->actAsUserWithoutPermissions();
        $this->getJson("/api/v1/leaves/requests/{$leaveRequest->id}/document")->assertForbidden();
    }

    public function test_portal_request_attachment_is_private_and_scoped(): void
    {
        $portalUser = $this->portalEmployee($this->company);
        $requestType = RequestType::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'requires_approval' => false,
            'requires_attachment' => false,
            'form_fields' => null,
        ]);

        $this->actAsPortal($portalUser);
        $store = $this->post('/api/v1/portal/requests', [
            'request_type_id' => $requestType->id,
            'title' => 'Adres değişikliği',
            'attachments' => [UploadedFile::fake()->create('ikametgah.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $requestId = $store->json('data.id');
        $path = (string) $store->json('data.attachments.0.path');
        $this->assertPrivateOnly($path);

        $this->get("/api/v1/portal/requests/{$requestId}/attachments/0")->assertOk();
        $this->getJson("/api/v1/portal/requests/{$requestId}/attachments/1")->assertNotFound();

        $this->actAsPortal($this->portalEmployee($this->company));
        $this->getJson("/api/v1/portal/requests/{$requestId}/attachments/0")->assertNotFound();
    }

    public function test_expense_receipt_is_private_and_scoped(): void
    {
        $portalUser = $this->portalEmployee($this->company);
        $claim = ExpenseClaim::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $portalUser->id,
            'status' => ExpenseClaim::STATUS_DRAFT,
        ]);
        $item = ExpenseItem::factory()->create(['expense_claim_id' => $claim->id, 'receipt_path' => null]);

        $this->actAsPortal($portalUser);
        $upload = $this->post("/api/v1/portal/expenses/items/{$item->id}/receipt", [
            'receipt' => UploadedFile::fake()->create('fis.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertPrivateOnly((string) $item->fresh()->receipt_path);
        $this->assertStringNotContainsString('/storage/', (string) $upload->json('data.receipt_url'));

        $this->get("/api/v1/portal/expenses/items/{$item->id}/receipt")->assertOk();

        $this->actAsPortal($this->portalEmployee($this->company));
        $this->getJson("/api/v1/portal/expenses/items/{$item->id}/receipt")->assertNotFound();

        $this->actAsAdmin();
        $this->get("/api/v1/expenses/claims/{$claim->id}/items/{$item->id}/receipt")->assertOk();

        $this->actAsOtherCompanyAdmin();
        $this->getJson("/api/v1/expenses/claims/{$claim->id}/items/{$item->id}/receipt")->assertNotFound();
    }

    public function test_public_application_cv_is_private_and_downloadable_by_recruiter_only(): void
    {
        $position = JobPosition::create([
            'company_id' => $this->company->id,
            'title' => 'Muhasebe Uzmanı',
            'slug' => 'muhasebe-'.Str::lower(Str::random(6)),
            'status' => JobPositionStatus::Active,
            'employment_type' => 'full_time',
            'experience_level' => 'mid',
        ]);

        $this->post("/api/v1/public/companies/{$this->company->slug}/jobs/{$position->slug}/apply", [
            'first_name' => 'Zeynep',
            'last_name' => 'Kaya',
            'email' => 'zeynep.kaya@example.com',
            'consent_kvkk' => 1,
            'cv' => UploadedFile::fake()->create('zeynep-cv.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $application = JobApplication::query()->where('email', 'zeynep.kaya@example.com')->firstOrFail();
        $this->assertPrivateOnly((string) $application->cv_path);

        $this->actAsAdmin();
        $download = $this->get("/api/v1/recruitment/applications/{$application->id}/cv")->assertOk();
        $this->assertStringContainsString('zeynep-cv.pdf', (string) $download->headers->get('content-disposition'));

        $detail = $this->getJson("/api/v1/recruitment/applications/{$application->id}")->assertOk();
        $this->assertStringEndsWith("/api/v1/recruitment/applications/{$application->id}/cv", (string) $detail->json('data.cv_path'));

        $pool = $this->getJson('/api/v1/recruitment/cv-pool')->assertOk();
        $this->assertSame($application->id, $pool->json('data.0.cv_application_id'));

        $this->actAsOtherCompanyAdmin();
        $this->getJson("/api/v1/recruitment/applications/{$application->id}/cv")->assertNotFound();

        $this->actAsUserWithoutPermissions();
        $this->getJson("/api/v1/recruitment/applications/{$application->id}/cv")->assertForbidden();
    }

    public function test_legacy_public_file_is_still_readable_and_move_command_relocates_it(): void
    {
        $path = 'documents/'.$this->company->id.'/eski-'.Str::lower(Str::random(8)).'.pdf';
        $this->createdPaths[] = $path;
        Storage::disk('public')->put($path, 'eski belge');
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'file_path' => $path,
            'file_name' => 'eski.pdf',
            'file_type' => 'application/pdf',
            'uploaded_by' => $this->admin->id,
        ]);

        $this->actAsAdmin();
        $this->get("/api/v1/documents/{$document->id}/download")->assertOk();

        $this->artisan('storage:move-sensitive-to-private', ['--dry-run' => true])->assertSuccessful();
        $this->assertFileExists(storage_path('app/public/'.$path));
        $this->assertFileDoesNotExist(storage_path('app/private/'.$path));

        $this->artisan('storage:move-sensitive-to-private')->assertSuccessful();
        $this->assertPrivateOnly($path);
        $this->assertSame('eski belge', Storage::disk('private')->get($path));

        $this->artisan('storage:move-sensitive-to-private')->assertSuccessful();
        $this->assertPrivateOnly($path);

        $this->get("/api/v1/documents/{$document->id}/download")->assertOk();
    }

    private function assertPrivateOnly(string $path): void
    {
        $this->assertNotSame('', $path);
        $this->createdPaths[] = $path;
        $this->assertFileExists(storage_path('app/private/'.$path));
        $this->assertFileDoesNotExist(storage_path('app/public/'.$path));
        $this->get('/storage/'.$path)->assertNotFound();
    }

    private function actAsAdmin(): void
    {
        $this->resetAuth();
        $this->actingAsInCompany($this->admin, $this->company);
    }

    private function actAsPortal(User $user): void
    {
        $this->resetAuth();
        Sanctum::actingAs($user, ['portal:access']);
    }

    private function actAsOtherCompanyAdmin(): void
    {
        $other = $this->otherCompany();
        $this->enableModules($other);
        $this->resetAuth();
        $this->actingAsInCompany($this->companyAdminFor($other), $other);
    }

    private function actAsUserWithoutPermissions(): void
    {
        $this->resetAuth();
        $this->actingAsInCompany($this->userWithPermissions($this->company), $this->company);
    }

    private function resetAuth(): void
    {
        auth()->forgetGuards();
        $this->flushHeaders();
    }
}
