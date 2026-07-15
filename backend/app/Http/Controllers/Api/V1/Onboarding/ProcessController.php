<?php

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\OnboardingProcess;
use App\Models\OnboardingTask;
use App\Services\LookupService;
use App\Services\Onboarding\OnboardingProcessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
        protected OnboardingProcessService $processService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OnboardingProcess::with(['user', 'template', 'assignedTo'])
            ->withCount('tasks');

        if ($request->filled('status')) {
            $this->lookups->assertValid(
                LookupService::TYPE_ONBOARDING_PROCESS_STATUS,
                $request->string('status')->toString(),
                $this->getCompanyId(),
                'status'
            );
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $processes = $query->latest()->paginate($request->get('per_page', 15));

        return $this->success($processes, 'Onboarding süreçleri listelendi');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'template_id' => 'nullable|exists:onboarding_templates,id',
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'target_end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $process = $this->processService->startProcess([
            ...$validated,
            'company_id' => $this->getCompanyId(),
            'created_by' => auth()->id(),
        ]);

        return $this->success($process, 'Onboarding süreci oluşturuldu', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(OnboardingProcess $process): JsonResponse
    {
        return $this->success(
            $process->load(['user', 'template', 'tasks.assignedTo', 'tasks.completedBy', 'assignedTo']),
            'Onboarding süreci detayları'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OnboardingProcess $process): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'target_end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'notes' => 'sometimes|nullable|string',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
            'status' => 'sometimes|string|max:100',
        ]);

        $this->lookups->assertValid(
            LookupService::TYPE_ONBOARDING_PROCESS_STATUS,
            $validated['status'] ?? null,
            $this->getCompanyId(),
            'status'
        );

        $oldValues = $process->getOriginal();
        $process->update($validated);

        ActivityLog::log('update', $process, 'Onboarding süreci güncellendi: '.$process->title, $oldValues, $process->fresh()->toArray());

        return $this->success($process, 'Onboarding süreci güncellendi');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OnboardingProcess $process): JsonResponse
    {
        $processTitle = $process->title;
        ActivityLog::log('delete', null, 'Onboarding süreci silindi: '.$processTitle);

        $process->tasks()->delete();
        $process->delete();

        return $this->success(null, 'Onboarding süreci silindi');
    }

    /**
     * Add a task to process.
     */
    public function addTask(Request $request, OnboardingProcess $process): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:document_upload,document_fill,training,meeting,system_setup,quiz,custom',
            'is_required' => 'boolean',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $maxOrder = $process->tasks()->max('order') ?? -1;

        $task = OnboardingTask::create(array_merge($validated, [
            'company_id' => $this->getCompanyId(),
            'process_id' => $process->id,
            'order' => $maxOrder + 1,
            'status' => OnboardingTask::STATUS_PENDING,
        ]));

        $process->updateProgress();

        ActivityLog::log('create', $task, 'Onboarding görevi eklendi: '.$task->title);

        if ($task->assigned_to) {
            app(\App\Services\Notification\NotificationService::class)
                ->notifyOnboardingTaskAssigned($task->load('process'));
        }

        return $this->success($task, 'Görev eklendi', 201);
    }

    /**
     * Complete a task.
     */
    public function completeTask(Request $request, OnboardingProcess $process, OnboardingTask $task): JsonResponse
    {
        if ($task->process_id !== $process->id) {
            return $this->error('Görev bu sürece ait değil', null, 404);
        }

        $validated = $request->validate([
            'data' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $task->complete(auth()->id(), $validated['data'] ?? null);

        if (isset($validated['notes'])) {
            $task->update(['notes' => $validated['notes']]);
        }

        ActivityLog::log('update', $task, 'Onboarding görevi tamamlandı: '.$task->title);

        return $this->success($task->fresh(), 'Görev tamamlandı');
    }

    /**
     * Skip a task.
     */
    public function skipTask(OnboardingProcess $process, OnboardingTask $task): JsonResponse
    {
        if ($task->process_id !== $process->id) {
            return $this->error('Görev bu sürece ait değil', null, 404);
        }

        if ($task->is_required) {
            return $this->error('Zorunlu görevler atlanamaz', null, 422);
        }

        $task->skip();

        ActivityLog::log('update', $task, 'Onboarding görevi atlandı: '.$task->title);

        return $this->success($task->fresh(), 'Görev atlandı');
    }

    /**
     * Get active processes dashboard.
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_active' => OnboardingProcess::active()->count(),
            'pending' => OnboardingProcess::where('status', OnboardingProcess::STATUS_PENDING)->count(),
            'in_progress' => OnboardingProcess::where('status', OnboardingProcess::STATUS_IN_PROGRESS)->count(),
            'completed_this_month' => OnboardingProcess::where('status', OnboardingProcess::STATUS_COMPLETED)
                ->whereMonth('actual_end_date', now()->month)
                ->count(),
            'overdue_tasks' => OnboardingTask::where('status', OnboardingTask::STATUS_PENDING)
                ->where('due_date', '<', now())
                ->where('is_required', true)
                ->count(),
        ];

        $recentProcesses = OnboardingProcess::with(['user', 'assignedTo'])
            ->active()
            ->latest()
            ->limit(5)
            ->get();

        return $this->success([
            'stats' => $stats,
            'recent_processes' => $recentProcesses,
        ], 'Onboarding dashboard');
    }
}
