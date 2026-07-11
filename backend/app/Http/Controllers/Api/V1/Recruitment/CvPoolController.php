<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CvPoolController extends BaseController
{
    /**
     * CV havuzu - tüm adaylar (email bazlı gruplama).
     * Şema: first_name, last_name, email, phone (applicant_* kolonları yok).
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobApplication::query()
            ->select('email', 'first_name', 'last_name', 'phone')
            ->selectRaw('MAX(id) as id')
            ->selectRaw('MAX(cv_path) as cv_path')
            ->selectRaw('MAX(rating) as rating')
            ->selectRaw('MAX(created_at) as last_application_date')
            ->groupBy('email', 'first_name', 'last_name', 'phone');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $candidates = $query->orderByRaw('MAX(created_at) DESC')
            ->limit(100)
            ->get()
            ->map(function ($candidate) {
                $latestApplication = JobApplication::where('email', $candidate->email)
                    ->orderBy('created_at', 'desc')
                    ->first();

                return [
                    'id' => $candidate->id,
                    'name' => trim(($candidate->first_name ?? '').' '.($candidate->last_name ?? '')),
                    'email' => $candidate->email,
                    'phone' => $candidate->phone,
                    'city' => $latestApplication?->form_data['city'] ?? null,
                    'experience_years' => $latestApplication?->form_data['experience_years'] ?? null,
                    'education_level' => $latestApplication?->form_data['education_level'] ?? null,
                    'skills' => $latestApplication?->form_data['skills'] ?? [],
                    'tags' => $latestApplication?->form_data['tags'] ?? [],
                    'rating' => $candidate->rating,
                    'status' => $latestApplication?->status,
                    'cv_path' => $candidate->cv_path ? asset('storage/'.$candidate->cv_path) : null,
                    'last_application_date' => $candidate->last_application_date,
                    'created_at' => $latestApplication?->created_at?->toDateTimeString(),
                ];
            });

        return $this->success($candidates);
    }

    /**
     * Toplu etiketleme (form_data.tags içinde)
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
                $formData = $application->form_data ?? [];
                $tags = $formData['tags'] ?? [];
                if (! in_array($validated['tag'], $tags, true)) {
                    $tags[] = $validated['tag'];
                    $formData['tags'] = $tags;
                    $application->update(['form_data' => $formData]);
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

        if (! $application) {
            return $this->notFound('Aday bulunamadı');
        }

        $validated = $request->validate([
            'tag' => 'required|string',
        ]);

        $formData = $application->form_data ?? [];
        $oldTags = $formData['tags'] ?? [];
        $formData['tags'] = array_values(array_filter($oldTags, fn ($t) => $t !== $validated['tag']));
        $application->update(['form_data' => $formData]);

        ActivityLog::log('update', $application, "Etiket kaldırıldı: {$validated['tag']}", ['tags' => $oldTags], ['tags' => $formData['tags']]);

        return $this->success(null, 'Etiket kaldırıldı');
    }

    /**
     * Aday puanla
     */
    public function rate(Request $request, int $id): JsonResponse
    {
        $application = JobApplication::find($id);

        if (! $application) {
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
