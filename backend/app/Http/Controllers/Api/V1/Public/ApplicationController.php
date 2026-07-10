<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\JobApplication;
use App\Models\JobPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApplicationController extends Controller
{
    /**
     * Başvuru gönder (public)
     */
    public function store(Request $request, string $positionSlug): JsonResponse
    {
        $position = JobPosition::with(['company', 'form'])
            ->where('slug', $positionSlug)
            ->where('status', 'published')
            ->first();

        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Pozisyon bulunamadı veya başvurular kapatılmış',
            ], 404);
        }

        // Form alanlarından temel bilgileri çıkar
        $formFields = $position->form?->fields ?? [];
        $formData = [];
        $applicantName = null;
        $applicantEmail = null;
        $applicantPhone = null;
        $cvPath = null;

        foreach ($formFields as $field) {
            $fieldId = $field['id'];
            $value = $request->input($fieldId);

            if ($field['type'] === 'file' && $request->hasFile($fieldId)) {
                $file = $request->file($fieldId);
                $path = $file->store('applications/'.$position->company_id, 'public');

                // CV dosyası ise kaydet
                if (str_contains(strtolower($field['label']), 'cv') || str_contains(strtolower($field['label']), 'özgeçmiş')) {
                    $cvPath = $path;
                }

                $formData[$field['label']] = $path;
            } else {
                $formData[$field['label']] = $value;
            }

            // Temel bilgileri tespit et
            $labelLower = strtolower($field['label']);
            if ($field['type'] === 'email' || str_contains($labelLower, 'e-posta') || str_contains($labelLower, 'email')) {
                $applicantEmail = $value;
            }
            if (str_contains($labelLower, 'ad') && str_contains($labelLower, 'soyad') || str_contains($labelLower, 'isim')) {
                $applicantName = $value;
            }
            if ($field['type'] === 'phone' || str_contains($labelLower, 'telefon') || str_contains($labelLower, 'tel')) {
                $applicantPhone = $value;
            }
        }

        // Zorunlu alanları kontrol et
        if (! $applicantEmail) {
            $applicantEmail = $request->input('email', $request->input('applicant_email'));
        }
        if (! $applicantName) {
            $applicantName = $request->input('name', $request->input('applicant_name', 'İsimsiz Başvuru'));
        }

        try {
            $application = JobApplication::create([
                'company_id' => $position->company_id,
                'position_id' => $position->id,
                'form_id' => $position->form_id,
                'applicant_name' => $applicantName,
                'applicant_email' => $applicantEmail,
                'applicant_phone' => $applicantPhone,
                'form_data' => $formData,
                'cv_path' => $cvPath,
                'status' => 'new',
                'source' => 'website',
            ]);

            Log::info('Yeni başvuru alındı', [
                'application_id' => $application->id,
                'position' => $position->title,
                'applicant' => $applicantName,
            ]);

            // Activity log kaydı (public başvuru için user_id null olabilir)
            ActivityLog::log('create', $application, "Yeni başvuru alındı: {$applicantName} - {$position->title}");

            return response()->json([
                'success' => true,
                'message' => 'Başvurunuz başarıyla alındı',
                'data' => [
                    'id' => $application->id,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Başvuru kaydetme hatası', [
                'error' => $e->getMessage(),
                'position_id' => $position->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Başvuru kaydedilemedi. Lütfen tekrar deneyin.',
            ], 500);
        }
    }
}
