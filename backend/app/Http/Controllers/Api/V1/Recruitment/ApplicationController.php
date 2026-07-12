<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\ApplicationStatusLog;
use App\Models\JobApplication;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends BaseController
{
    public function __construct(
        protected LookupService $lookups,
    ) {}
    /**
     * Başvuru listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobApplication::with(['position', 'creator']);

        // Durum filtresi
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Arama
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('applicant_name', 'like', "%{$search}%")
                    ->orWhere('applicant_email', 'like', "%{$search}%")
                    ->orWhere('applicant_phone', 'like', "%{$search}%");
            });
        }

        // Pozisyon filtresi
        if ($request->filled('position_id')) {
            $query->where('position_id', $request->position_id);
        }

        $applications = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        $data = $applications->through(function ($app) {
            return [
                'id' => $app->id,
                'applicant_name' => $app->applicant_name,
                'applicant_email' => $app->applicant_email,
                'applicant_phone' => $app->applicant_phone,
                'position' => $app->position ? [
                    'id' => $app->position->id,
                    'title' => $app->position->title,
                ] : null,
                'status' => $app->status,
                'form_data' => $app->form_data ?? [],
                'cv_path' => $app->cv_path ? asset('storage/'.$app->cv_path) : null,
                'notes' => $app->notes,
                'rating' => $app->rating,
                'created_at' => $app->created_at->toDateTimeString(),
            ];
        });

        return $this->success($data);
    }

    /**
     * Başvuru detayı
     */
    public function show(int $id): JsonResponse
    {
        $application = JobApplication::with(['position', 'statusLogs.user'])->find($id);

        if (! $application) {
            return $this->notFound('Başvuru bulunamadı');
        }

        return $this->success([
            'id' => $application->id,
            'applicant_name' => $application->applicant_name,
            'applicant_email' => $application->applicant_email,
            'applicant_phone' => $application->applicant_phone,
            'position' => $application->position ? [
                'id' => $application->position->id,
                'title' => $application->position->title,
            ] : null,
            'status' => $application->status,
            'form_data' => $application->form_data ?? [],
            'cv_path' => $application->cv_path ? asset('storage/'.$application->cv_path) : null,
            'notes' => $application->notes,
            'rating' => $application->rating,
            'status_logs' => $application->statusLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'status' => $log->status,
                    'notes' => $log->notes,
                    'user' => $log->user ? $log->user->name : null,
                    'created_at' => $log->created_at->toDateTimeString(),
                ];
            }),
            'created_at' => $application->created_at->toDateTimeString(),
        ]);
    }

    /**
     * Başvuru durumu güncelle
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (! $application) {
            return $this->notFound('Başvuru bulunamadı');
        }

        $validated = $request->validate([
            'status' => 'required|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $companyId = $this->getCompanyId();
        $this->lookups->assertValid(
            LookupService::TYPE_APPLICATION_STAGE,
            $validated['status'],
            $companyId,
            'status'
        );

        $oldStatus = $application->status instanceof \BackedEnum
            ? $application->status->value
            : (string) $application->status;
        $application->update(['status' => $validated['status']]);

        // Durum log kaydı
        ApplicationStatusLog::create([
            'job_application_id' => $application->id,
            'company_id' => $this->getCompanyId(),
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'changed_by' => auth()->id(),
        ]);

        ActivityLog::log('update', $application, "Başvuru durumu güncellendi: {$oldStatus} -> {$validated['status']}");

        return $this->success($application, 'Durum güncellendi');
    }

    /**
     * Başvuru notlarını güncelle
     */
    public function updateNotes(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (! $application) {
            return $this->notFound('Başvuru bulunamadı');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $oldNotes = $application->notes;
        $application->update(['notes' => $validated['notes']]);

        ActivityLog::log('update', $application, 'Başvuru notları güncellendi', ['notes' => $oldNotes], ['notes' => $validated['notes']]);

        return $this->success($application, 'Notlar güncellendi');
    }

    /**
     * Başvuru puanla
     */
    public function rate(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (! $application) {
            return $this->notFound('Başvuru bulunamadı');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $oldRating = $application->rating;
        $application->update(['rating' => $validated['rating']]);

        ActivityLog::log('update', $application, "Başvuru puanlandı: {$oldRating} -> {$validated['rating']}", ['rating' => $oldRating], ['rating' => $validated['rating']]);

        return $this->success($application, 'Puan güncellendi');
    }
}
