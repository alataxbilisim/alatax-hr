<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\JobApplication;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CvPoolController extends BaseController
{
    /**
     * CV havuzu - tüm adaylar
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobApplication::select(
            'applicant_email',
            'applicant_name',
            'applicant_phone',
        )
        ->selectRaw('MAX(id) as id')
        ->selectRaw('MAX(cv_path) as cv_path')
        ->selectRaw('MAX(rating) as rating')
        ->selectRaw('MAX(created_at) as last_application_date')
        ->groupBy('applicant_email', 'applicant_name', 'applicant_phone');

        // Arama
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('applicant_name', 'like', "%{$search}%")
                  ->orWhere('applicant_email', 'like', "%{$search}%");
            });
        }

        $candidates = $query->orderBy('last_application_date', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($candidate) {
                // Aday bilgilerini en son başvurudan al
                $latestApplication = JobApplication::where('applicant_email', $candidate->applicant_email)
                    ->orderBy('created_at', 'desc')
                    ->first();

                return [
                    'id' => $candidate->id,
                    'name' => $candidate->applicant_name,
                    'email' => $candidate->applicant_email,
                    'phone' => $candidate->applicant_phone,
                    'city' => $latestApplication->form_data['city'] ?? null,
                    'experience_years' => $latestApplication->form_data['experience_years'] ?? null,
                    'education_level' => $latestApplication->form_data['education_level'] ?? null,
                    'skills' => $latestApplication->form_data['skills'] ?? [],
                    'tags' => $latestApplication->tags ?? [],
                    'rating' => $candidate->rating,
                    'status' => $latestApplication->status,
                    'cv_path' => $candidate->cv_path ? asset('storage/' . $candidate->cv_path) : null,
                    'last_application_date' => $candidate->last_application_date,
                    'created_at' => $latestApplication->created_at->toDateTimeString(),
                ];
            });

        return $this->success($candidates);
    }

    /**
     * Toplu etiketleme
     */
    public function bulkTag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'candidate_ids' => 'required|array',
            'candidate_ids.*' => 'integer',
            'tag' => 'required|string|max:50',
        ]);

        $updated = 0;
        foreach ($validated['candidate_ids'] as $id) {
            $application = JobApplication::find($id);
            if ($application) {
                $tags = $application->tags ?? [];
                if (!in_array($validated['tag'], $tags)) {
                    $tags[] = $validated['tag'];
                    $application->update(['tags' => $tags]);
                    ActivityLog::log('update', $application, "Etiket eklendi: {$validated['tag']}");
                    $updated++;
                }
            }
        }

        return $this->success(['updated' => $updated], "Etiket {$updated} adaya eklendi");
    }

    /**
     * Etiket kaldır
     */
    public function removeTag(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (!$application) {
            return $this->notFound('Aday bulunamadı');
        }

        $validated = $request->validate([
            'tag' => 'required|string',
        ]);

        $oldTags = $application->tags ?? [];
        $tags = array_values(array_filter($oldTags, fn($t) => $t !== $validated['tag']));
        $application->update(['tags' => $tags]);

        ActivityLog::log('update', $application, "Etiket kaldırıldı: {$validated['tag']}", ['tags' => $oldTags], ['tags' => $tags]);

        return $this->success(null, 'Etiket kaldırıldı');
    }

    /**
     * Aday puanla
     */
    public function rate(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (!$application) {
            return $this->notFound('Aday bulunamadı');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $oldRating = $application->rating;
        $application->update(['rating' => $validated['rating']]);

        ActivityLog::log('update', $application, "CV havuzu adayı puanlandı: {$oldRating} -> {$validated['rating']}", ['rating' => $oldRating], ['rating' => $validated['rating']]);

        return $this->success(null, 'Puan güncellendi');
    }
}
