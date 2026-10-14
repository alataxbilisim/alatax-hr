<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Module;
use App\Models\OnboardingProcess;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\User;
use Tests\Concerns\CreatesOtherCompanyFixture;
use Tests\Concerns\CreatesUsersWithPermissions;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\SetsCompanyContext;
use Tests\TestCase;

/**
 * K5: POST onboarding/templates/{id}/duplicate ve POST onboarding/processes/{id}/tasks/{task}/skip.
 */
class OnboardingDuplicateAndSkipTest extends TestCase
{
    use CreatesOtherCompanyFixture;
    use CreatesUsersWithPermissions;
    use RefreshDatabase;
    use SetsCompanyContext;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->createActiveCompany();
        $this->enableOnboarding($this->company);
    }

    private function enableOnboarding(Company $company): void
    {
        $module = Module::firstOrCreate(['slug' => 'onboarding'], ['name' => 'onboarding', 'is_core' => false, 'is_active' => true]);
        $company->modules()->syncWithoutDetaching([$module->id => ['is_active' => true, 'activated_at' => now()]]);
    }

    private function template(Company $company, array $attributes = []): OnboardingTemplate
    {
        return OnboardingTemplate::withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'process_type' => OnboardingTemplate::TYPE_ONBOARDING,
            'name' => 'Yeni Başlayan',
            'tasks' => [['title' => 'Sözleşme', 'type' => 'document_upload', 'is_required' => true]],
            'estimated_days' => 5,
            'is_active' => true,
            'is_default' => true,
        ], $attributes));
    }

    private function process(Company $company): OnboardingProcess
    {
        $user = User::factory()->create(['home_company_id' => $company->id]);

        return OnboardingProcess::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'process_type' => OnboardingProcess::TYPE_ONBOARDING,
            'user_id' => $user->id,
            'title' => 'Oryantasyon',
            'start_date' => now()->toDateString(),
            'status' => OnboardingProcess::STATUS_IN_PROGRESS,
            'progress' => 0,
        ]);
    }

    private function task(OnboardingProcess $process, bool $required = false, string $status = OnboardingTask::STATUS_PENDING): OnboardingTask
    {
        return OnboardingTask::withoutGlobalScopes()->create([
            'company_id' => $process->company_id,
            'process_id' => $process->id,
            'title' => $required ? 'Zorunlu görev' : 'İsteğe bağlı görev',
            'type' => OnboardingTask::TYPE_CUSTOM,
            'order' => 0,
            'is_required' => $required,
            'status' => $status,
        ]);
    }

    private function actAs(array $permissions): User
    {
        $user = $this->userWithPermissions($this->company, $permissions);
        $this->actingAsInCompany($user, $this->company);

        return $user;
    }

    // ——— duplicate ———

    public function test_duplicate_requires_authentication(): void
    {
        $template = $this->template($this->company);

        $this->postJson("/api/v1/onboarding/templates/{$template->id}/duplicate")->assertStatus(401);
    }

    public function test_duplicate_requires_create_permission(): void
    {
        $template = $this->template($this->company);
        $this->actAs(['onboarding.templates.view']);

        $this->postJson("/api/v1/onboarding/templates/{$template->id}/duplicate")->assertStatus(403);
    }

    public function test_duplicate_creates_non_default_copy_with_audit_log(): void
    {
        $template = $this->template($this->company);
        $this->actAs(['onboarding.templates.create']);

        $response = $this->postJson("/api/v1/onboarding/templates/{$template->id}/duplicate")
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Yeni Başlayan (Kopya)')
            ->assertJsonPath('data.is_default', false);

        $copy = OnboardingTemplate::withoutGlobalScopes()->findOrFail($response->json('data.id'));
        $this->assertNotSame($template->id, $copy->id);
        $this->assertSame($this->company->id, $copy->company_id);
        $this->assertEquals($template->fresh()->tasks, $copy->tasks);
        $this->assertTrue($template->fresh()->is_default);
        $this->assertTrue(ActivityLog::query()->where('model_type', $copy->getMorphClass())->where('model_id', $copy->id)->exists());

        $this->postJson("/api/v1/onboarding/templates/{$template->id}/duplicate", ['name' => 'Saha Ekibi'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Saha Ekibi');
    }

    public function test_duplicate_of_other_company_template_returns_404(): void
    {
        $other = $this->otherCompany();
        $this->enableOnboarding($other);
        $foreign = $this->template($other);
        $this->actAs(['onboarding.templates.create']);

        $this->postJson("/api/v1/onboarding/templates/{$foreign->id}/duplicate")->assertStatus(404);
        $this->assertSame(1, OnboardingTemplate::withoutGlobalScopes()->where('company_id', $other->id)->count());
    }

    // ——— skip ———

    public function test_skip_requires_authentication(): void
    {
        $process = $this->process($this->company);
        $task = $this->task($process);

        $this->postJson("/api/v1/onboarding/processes/{$process->id}/tasks/{$task->id}/skip")->assertStatus(401);
    }

    public function test_skip_requires_edit_permission(): void
    {
        $process = $this->process($this->company);
        $task = $this->task($process);
        $this->actAs(['onboarding.processes.view']);

        $this->postJson("/api/v1/onboarding/processes/{$process->id}/tasks/{$task->id}/skip")->assertStatus(403);
        $this->assertSame(OnboardingTask::STATUS_PENDING, $task->fresh()->status);
    }

    public function test_skip_marks_optional_task_skipped_with_notes_and_audit_log(): void
    {
        $process = $this->process($this->company);
        $task = $this->task($process);
        $this->actAs(['onboarding.processes.edit']);

        $this->postJson("/api/v1/onboarding/processes/{$process->id}/tasks/{$task->id}/skip", ['notes' => 'Uzaktan çalışan'])
            ->assertOk()
            ->assertJsonPath('data.status', OnboardingTask::STATUS_SKIPPED)
            ->assertJsonPath('data.notes', 'Uzaktan çalışan');

        $this->assertTrue(ActivityLog::query()->where('model_type', $task->getMorphClass())->where('model_id', $task->id)->exists());
    }

    public function test_skip_rejects_required_or_completed_task(): void
    {
        $process = $this->process($this->company);
        $required = $this->task($process, required: true);
        $completed = $this->task($process, status: OnboardingTask::STATUS_COMPLETED);
        $this->actAs(['onboarding.processes.edit']);

        $this->postJson("/api/v1/onboarding/processes/{$process->id}/tasks/{$required->id}/skip")->assertStatus(422);
        $this->postJson("/api/v1/onboarding/processes/{$process->id}/tasks/{$completed->id}/skip")->assertStatus(422);

        $this->assertSame(OnboardingTask::STATUS_PENDING, $required->fresh()->status);
        $this->assertSame(OnboardingTask::STATUS_COMPLETED, $completed->fresh()->status);
    }

    public function test_skip_task_of_another_process_returns_404(): void
    {
        $process = $this->process($this->company);
        $otherTask = $this->task($this->process($this->company));
        $this->actAs(['onboarding.processes.edit']);

        $this->postJson("/api/v1/onboarding/processes/{$process->id}/tasks/{$otherTask->id}/skip")->assertStatus(404);
    }

    public function test_skip_on_other_company_process_returns_404(): void
    {
        $other = $this->otherCompany();
        $this->enableOnboarding($other);
        $foreignProcess = $this->process($other);
        $foreignTask = $this->task($foreignProcess);
        $ownTask = $this->task($this->process($this->company));
        $this->actAs(['onboarding.processes.edit']);

        $this->postJson("/api/v1/onboarding/processes/{$foreignProcess->id}/tasks/{$foreignTask->id}/skip")->assertStatus(404);
        $this->postJson("/api/v1/onboarding/processes/{$ownTask->process_id}/tasks/{$foreignTask->id}/skip")->assertStatus(404);
        $this->assertSame(OnboardingTask::STATUS_PENDING, $foreignTask->fresh()->status);
    }
}
