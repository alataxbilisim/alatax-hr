<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\JobApplicationStatus;
use App\Enums\JobPositionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StorePublicApplicationRequest;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Services\PublicApplicationFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    public function __construct(
        protected PublicApplicationFormService $publicFormService,
    ) {}

    /**
     * Public başvuru — status=active (JobPositionStatus baskın doğruluk).
     * Tenant: company_slug zorunlu; pozisyon firma ile eşleşmeli.
     */
    public function store(StorePublicApplicationRequest $request, string $positionSlug): JsonResponse
    {
        $validated = $request->validated();
        // Route param adıyla oku (companies/{companySlug}/jobs/{positionSlug} enjeksiyon sırasına güvenme)
        $resolvedSlug = $request->route('positionSlug');
        $positionSlug = is_string($resolvedSlug) && $resolvedSlug !== '' ? $resolvedSlug : $positionSlug;

        $company = Company::query()
            ->where('slug', $validated['company_slug'])
            ->first();

        if ($company === null) {
            return response()->json([
                'success' => false,
                'message' => 'Firma bulunamadı',
            ], 404);
        }

        $position = JobPosition::query()
            ->with(['form'])
            ->where('slug', $positionSlug)
            ->where('company_id', $company->id)
            ->where('status', JobPositionStatus::Active)
            ->where(function ($q) {
                $q->whereNull('application_deadline')
                    ->orWhere('application_deadline', '>=', now()->toDateString());
            })
            ->first();

        if ($position === null) {
            return response()->json([
                'success' => false,
                'message' => 'Pozisyon bulunamadı veya başvurular kapatılmış',
            ], 404);
        }

        try {
            $formData = $this->publicFormService->validateCustomFormData(
                $position,
                $validated['form_data'] ?? null
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Form alanları geçersiz',
                'errors' => $e->errors(),
            ], 422);
        }

        $cvPath = null;
        $cvOriginal = null;
        if ($request->hasFile('cv')) {
            $file = $request->file('cv');
            $cvPath = $file->store('applications/'.$company->id, 'public');
            $cvOriginal = $file->getClientOriginalName();
        }

        try {
            $application = JobApplication::create([
                'company_id' => $company->id,
                'job_position_id' => $position->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'cv_path' => $cvPath,
                'cv_original_name' => $cvOriginal,
                'form_data' => $formData !== [] ? $formData : ($validated['form_data'] ?? null),
                'status' => JobApplicationStatus::New,
                'source' => 'website',
                'consent_kvkk' => true,
                'consent_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            ActivityLog::log(
                'create',
                $application,
                "Public başvuru: {$application->first_name} {$application->last_name} — {$position->title}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Başvurunuz başarıyla alındı',
                'data' => [
                    'id' => $application->id,
                    'status' => JobApplicationStatus::New->value,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Public başvuru kaydetme hatası', [
                'error' => $e->getMessage(),
                'position_id' => $position->id,
                'company_id' => $company->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Başvuru kaydedilemedi. Lütfen tekrar deneyin.',
            ], 500);
        }
    }
}
