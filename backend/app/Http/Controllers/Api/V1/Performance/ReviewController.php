<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\PerformanceReview;
use App\Models\PerformanceScore;
use App\Services\DataScopeService;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
        protected LookupService $lookups,
    ) {}

    /**
     * Değerlendirme listesi
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PerformanceReview::class);

        $query = PerformanceReview::where('company_id', $this->getCompanyId())
            ->with(['period:id,name', 'employee:id,name,email', 'reviewer:id,name,email']);

        $this->dataScope->scopeForPerformanceReview($query, $request->user());

        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }

        if ($request->filled('status')) {
            $this->lookups->assertValid(
                LookupService::TYPE_PERFORMANCE_REVIEW_STATUS,
                $request->string('status')->toString(),
                $this->getCompanyId(),
                'status'
            );
            $query->where('status', $request->status);
        }

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $reviews = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return $this->success($reviews, 'Değerlendirmeler listelendi');
    }

    /**
     * Yeni değerlendirme oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PerformanceReview::class);

        $validated = $request->validate([
            'period_id' => 'required|exists:performance_periods,id',
            'employee_id' => 'required|exists:users,id',
            'reviewer_id' => 'required|exists:users,id',
        ]);

        // Aynı dönemde aynı personel için değerlendirme var mı?
        $exists = PerformanceReview::where('company_id', $this->getCompanyId())
            ->where('period_id', $validated['period_id'])
            ->where('employee_id', $validated['employee_id'])
            ->exists();

        if ($exists) {
            return $this->error('Bu dönemde bu personel için zaten bir değerlendirme mevcut.', 422);
        }

        $review = PerformanceReview::create([
            ...$validated,
            'company_id' => $this->getCompanyId(),
            'status' => 'draft',
        ]);

        ActivityLog::log('create', $review, 'Performans değerlendirmesi oluşturuldu');

        return $this->success($review->load(['period', 'employee', 'reviewer']), 'Değerlendirme oluşturuldu', 201);
    }

    /**
     * Değerlendirme detayı
     */
    public function show(int $id): JsonResponse
    {
        $review = PerformanceReview::where('company_id', $this->getCompanyId())
            ->with(['period', 'employee', 'reviewer', 'scores.criteria'])
            ->findOrFail($id);

        $this->authorize('view', $review);

        return $this->success($review);
    }

    /**
     * Değerlendirme güncelle (puanla)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $review = PerformanceReview::where('company_id', $this->getCompanyId())->findOrFail($id);

        $this->authorize('update', $review);

        if ($review->status === 'approved') {
            return $this->error('Onaylanmış değerlendirme düzenlenemez.', 422);
        }

        $validated = $request->validate([
            'strengths' => 'nullable|string',
            'improvements' => 'nullable|string',
            'goals' => 'nullable|string',
            'reviewer_comments' => 'nullable|string',
            'employee_comments' => 'nullable|string',
            'scores' => 'nullable|array',
            'scores.*.criteria_id' => 'required_with:scores|exists:performance_criteria,id',
            'scores.*.score' => 'required_with:scores|integer|min:0',
            'scores.*.comment' => 'nullable|string',
        ]);

        // Ana bilgileri güncelle
        $review->update([
            'strengths' => $validated['strengths'] ?? $review->strengths,
            'improvements' => $validated['improvements'] ?? $review->improvements,
            'goals' => $validated['goals'] ?? $review->goals,
            'reviewer_comments' => $validated['reviewer_comments'] ?? $review->reviewer_comments,
            'employee_comments' => $validated['employee_comments'] ?? $review->employee_comments,
        ]);

        // Puanları kaydet
        if (! empty($validated['scores'])) {
            foreach ($validated['scores'] as $scoreData) {
                PerformanceScore::updateOrCreate(
                    [
                        'review_id' => $review->id,
                        'criteria_id' => $scoreData['criteria_id'],
                    ],
                    [
                        'score' => $scoreData['score'],
                        'comment' => $scoreData['comment'] ?? null,
                    ]
                );
            }

            // Genel puanı hesapla
            $review->update([
                'overall_score' => $review->calculateOverallScore(),
            ]);
        }

        $oldValues = $review->getOriginal();
        ActivityLog::log('update', $review, 'Değerlendirme puanlandı', $oldValues, $review->fresh()->toArray());

        return $this->success($review->load(['scores.criteria']), 'Değerlendirme güncellendi');
    }

    /**
     * Değerlendirme sil
     */
    public function destroy(int $id): JsonResponse
    {
        $review = PerformanceReview::where('company_id', $this->getCompanyId())->findOrFail($id);

        $this->authorize('delete', $review);

        if ($review->status === 'approved') {
            return $this->error('Onaylanmış değerlendirme silinemez.', 422);
        }

        ActivityLog::log('delete', null, 'Performans değerlendirmesi silindi');

        $review->delete();

        return $this->success(null, 'Değerlendirme silindi');
    }

    /**
     * Değerlendirmeyi gönder
     */
    public function submit(int $id): JsonResponse
    {
        $review = PerformanceReview::where('company_id', $this->getCompanyId())->findOrFail($id);

        $this->authorize('update', $review);

        if ($review->status !== 'draft') {
            return $this->error('Sadece taslak değerlendirmeler gönderilebilir.', 422);
        }

        // En az bir puan girilmiş mi?
        if ($review->scores()->count() === 0) {
            return $this->error('Değerlendirme göndermek için en az bir kriter puanlanmalıdır.', 422);
        }

        $review->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        ActivityLog::log('submitted', $review, 'Değerlendirme gönderildi');

        return $this->success($review, 'Değerlendirme gönderildi');
    }

    /**
     * Değerlendirmeyi onayla
     */
    public function approve(int $id): JsonResponse
    {
        $review = PerformanceReview::where('company_id', $this->getCompanyId())->findOrFail($id);

        $this->authorize('approve', $review);

        if ($review->status !== 'submitted') {
            return $this->error('Sadece gönderilmiş değerlendirmeler onaylanabilir.', 422);
        }

        $review->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        ActivityLog::log('approved', $review, 'Değerlendirme onaylandı');

        return $this->success($review, 'Değerlendirme onaylandı');
    }

    /**
     * Değerlendirmeyi reddet
     */
    public function reject(int $id, Request $request): JsonResponse
    {
        $review = PerformanceReview::where('company_id', $this->getCompanyId())->findOrFail($id);

        $this->authorize('approve', $review);

        if ($review->status !== 'submitted') {
            return $this->error('Sadece gönderilmiş değerlendirmeler reddedilebilir.', 422);
        }

        $review->update([
            'status' => 'rejected',
            'reviewer_comments' => $request->input('reason', $review->reviewer_comments),
        ]);

        ActivityLog::log('rejected', $review, 'Değerlendirme reddedildi');

        return $this->success($review, 'Değerlendirme reddedildi');
    }
}
