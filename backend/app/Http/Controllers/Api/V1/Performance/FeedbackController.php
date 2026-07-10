<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\FeedbackProvider;
use App\Models\FeedbackResponse;
use App\Models\ContinuousFeedback;
use App\Models\PerformanceReview;
use App\Models\PerformanceCriteria;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FeedbackController extends BaseController
{
    /**
     * 360 Değerlendirme için geri bildirim sağlayıcıları ekle
     */
    public function addProviders(Request $request, int $reviewId): JsonResponse
    {
        $review = PerformanceReview::where('company_id', $this->getCompanyId())->findOrFail($reviewId);

        $validated = $request->validate([
            'providers' => 'required|array|min:1',
            'providers.*.user_id' => 'required|exists:users,id',
            'providers.*.relationship' => 'required|in:self,manager,peer,direct_report,external',
            'providers.*.is_anonymous' => 'boolean',
            'deadline' => 'nullable|date|after:today',
        ]);

        $addedProviders = [];
        
        foreach ($validated['providers'] as $providerData) {
            // Zaten eklenmişse atla
            if (FeedbackProvider::where('performance_review_id', $review->id)
                ->where('provider_id', $providerData['user_id'])
                ->exists()) {
                continue;
            }

            $provider = FeedbackProvider::create([
                'performance_review_id' => $review->id,
                'provider_id' => $providerData['user_id'],
                'relationship' => $providerData['relationship'],
                'is_anonymous' => $providerData['is_anonymous'] ?? true,
                'status' => FeedbackProvider::STATUS_PENDING,
                'invited_at' => now(),
                'deadline' => $validated['deadline'] ?? null,
            ]);

            $addedProviders[] = $provider;
        }

        // Review'ı 360 olarak işaretle
        $review->update(['is_360_enabled' => true]);

        ActivityLog::log('update', $review, '360 değerlendirme sağlayıcıları eklendi');

        return $this->success($addedProviders, 'Geri bildirim sağlayıcıları eklendi');
    }

    /**
     * Bekleyen geri bildirim taleplerim
     */
    public function pendingFeedbacks(): JsonResponse
    {
        $feedbacks = FeedbackProvider::where('provider_id', auth()->id())
            ->whereIn('status', [FeedbackProvider::STATUS_PENDING, FeedbackProvider::STATUS_IN_PROGRESS])
            ->with([
                'review.user:id,name',
                'review.period:id,name'
            ])
            ->get();

        return $this->success($feedbacks, 'Bekleyen geri bildirimler');
    }

    /**
     * Geri bildirim formu getir
     */
    public function getFeedbackForm(int $providerId): JsonResponse
    {
        $provider = FeedbackProvider::where('provider_id', auth()->id())
            ->with([
                'review.user:id,name',
                'review.period.criteria',
                'responses'
            ])
            ->findOrFail($providerId);

        return $this->success($provider, 'Geri bildirim formu');
    }

    /**
     * Geri bildirim gönder
     */
    public function submitFeedback(Request $request, int $providerId): JsonResponse
    {
        $provider = FeedbackProvider::where('provider_id', auth()->id())
            ->findOrFail($providerId);

        if ($provider->status === FeedbackProvider::STATUS_SUBMITTED) {
            return $this->error('Bu geri bildirim zaten gönderildi', 400);
        }

        $validated = $request->validate([
            'responses' => 'required|array|min:1',
            'responses.*.criteria_id' => 'required|exists:performance_criteria,id',
            'responses.*.score' => 'required|integer|min:1|max:5',
            'responses.*.comment' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Mevcut yanıtları sil
            $provider->responses()->delete();

            // Yeni yanıtları kaydet
            foreach ($validated['responses'] as $response) {
                FeedbackResponse::create([
                    'feedback_provider_id' => $provider->id,
                    'performance_criteria_id' => $response['criteria_id'],
                    'score' => $response['score'],
                    'comment' => $response['comment'] ?? null,
                ]);
            }

            // Provider durumunu güncelle
            $provider->submit();

            // Review'daki skorları hesapla
            $this->calculateReviewScores($provider->review);

            DB::commit();

            ActivityLog::log('create', $provider, '360 geri bildirimi gönderildi');

            return $this->success($provider, 'Geri bildirim gönderildi');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Geri bildirim gönderilemedi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Geri bildirim reddet
     */
    public function declineFeedback(Request $request, int $providerId): JsonResponse
    {
        $provider = FeedbackProvider::where('provider_id', auth()->id())
            ->findOrFail($providerId);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $provider->decline($validated['reason']);

        ActivityLog::log('update', $provider, '360 geri bildirimi reddedildi');

        return $this->success($provider, 'Geri bildirim reddedildi');
    }

    /**
     * Review için 360 sonuçlarını getir
     */
    public function getReviewFeedbacks(int $reviewId): JsonResponse
    {
        $review = PerformanceReview::where('company_id', $this->getCompanyId())
            ->with([
                'feedbackProviders' => fn($q) => $q->where('status', FeedbackProvider::STATUS_SUBMITTED),
                'feedbackProviders.responses.criteria',
            ])
            ->findOrFail($reviewId);

        // Sonuçları grupla
        $results = [
            'self' => null,
            'manager' => null,
            'peer' => [],
            'direct_report' => [],
            'external' => [],
        ];

        foreach ($review->feedbackProviders as $provider) {
            $avgScore = $provider->getAverageScore();
            $data = [
                'provider_id' => $provider->id,
                'provider_name' => $provider->is_anonymous ? null : $provider->provider->name,
                'average_score' => $avgScore,
                'responses' => $provider->responses,
            ];

            if (in_array($provider->relationship, ['self', 'manager'])) {
                $results[$provider->relationship] = $data;
            } else {
                $results[$provider->relationship][] = $data;
            }
        }

        // Grup ortalamalarını hesapla
        $groupAverages = [
            'self' => $results['self']['average_score'] ?? null,
            'manager' => $results['manager']['average_score'] ?? null,
            'peer' => $this->calculateGroupAverage($results['peer']),
            'direct_report' => $this->calculateGroupAverage($results['direct_report']),
        ];

        return $this->success([
            'results' => $results,
            'group_averages' => $groupAverages,
            'review' => $review,
        ], '360 sonuçları');
    }

    protected function calculateGroupAverage(array $group): ?float
    {
        if (empty($group)) {
            return null;
        }

        $scores = array_filter(array_column($group, 'average_score'));
        return empty($scores) ? null : array_sum($scores) / count($scores);
    }

    protected function calculateReviewScores(PerformanceReview $review): void
    {
        $providers = $review->feedbackProviders()->where('status', FeedbackProvider::STATUS_SUBMITTED)->get();

        $selfScore = $providers->where('relationship', 'self')->first()?->getAverageScore();
        $managerScore = $providers->where('relationship', 'manager')->first()?->getAverageScore();
        
        $peerScores = $providers->where('relationship', 'peer')->map(fn($p) => $p->getAverageScore())->filter();
        $peerScore = $peerScores->isNotEmpty() ? $peerScores->avg() : null;
        
        $reportScores = $providers->where('relationship', 'direct_report')->map(fn($p) => $p->getAverageScore())->filter();
        $reportScore = $reportScores->isNotEmpty() ? $reportScores->avg() : null;

        // Ağırlıklı ortalama (varsayılan ağırlıklar)
        $weights = [
            'self' => 0.1,
            'manager' => 0.4,
            'peer' => 0.3,
            'direct_report' => 0.2,
        ];

        $finalScore = 0;
        $totalWeight = 0;

        if ($selfScore !== null) {
            $finalScore += $selfScore * $weights['self'];
            $totalWeight += $weights['self'];
        }
        if ($managerScore !== null) {
            $finalScore += $managerScore * $weights['manager'];
            $totalWeight += $weights['manager'];
        }
        if ($peerScore !== null) {
            $finalScore += $peerScore * $weights['peer'];
            $totalWeight += $weights['peer'];
        }
        if ($reportScore !== null) {
            $finalScore += $reportScore * $weights['direct_report'];
            $totalWeight += $weights['direct_report'];
        }

        $review->update([
            'self_score' => $selfScore,
            'manager_score' => $managerScore,
            'peer_score' => $peerScore,
            'report_score' => $reportScore,
            'final_score' => $totalWeight > 0 ? $finalScore / $totalWeight : null,
        ]);
    }

    // ===== Sürekli Geri Bildirim =====

    /**
     * Sürekli geri bildirim listesi
     */
    public function continuousFeedbacks(Request $request): JsonResponse
    {
        $query = ContinuousFeedback::where('company_id', $this->getCompanyId())
            ->with(['fromUser:id,name', 'toUser:id,name']);

        if ($request->has('to_user_id')) {
            $query->where('to_user_id', $request->to_user_id);
        }

        if ($request->has('from_user_id')) {
            $query->where('from_user_id', $request->from_user_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Sadece public veya kendisine ait olanları göster
        $userId = auth()->id();
        $query->where(function ($q) use ($userId) {
            $q->where('is_public', true)
                ->orWhere('to_user_id', $userId)
                ->orWhere('from_user_id', $userId);
        });

        $feedbacks = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->paginated($feedbacks, 'Sürekli geri bildirimler');
    }

    /**
     * Sürekli geri bildirim gönder
     */
    public function sendContinuousFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'type' => 'required|in:praise,suggestion,concern,coaching',
            'content' => 'required|string|max:2000',
            'tags' => 'nullable|array',
            'is_public' => 'boolean',
            'is_anonymous' => 'boolean',
        ]);

        $feedback = ContinuousFeedback::create([
            'company_id' => $this->getCompanyId(),
            'from_user_id' => auth()->id(),
            'to_user_id' => $validated['to_user_id'],
            'type' => $validated['type'],
            'content' => $validated['content'],
            'tags' => $validated['tags'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
            'is_anonymous' => $validated['is_anonymous'] ?? false,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $feedback, 'Sürekli geri bildirim gönderildi');

        return $this->created($feedback, 'Geri bildirim gönderildi');
    }

    /**
     * Geri bildirim tiplerini getir
     */
    public function getFeedbackTypes(): JsonResponse
    {
        return $this->success([
            'types' => ContinuousFeedback::getTypeLabels(),
            'colors' => ContinuousFeedback::getTypeColors(),
            'relationships' => FeedbackProvider::getRelationshipLabels(),
        ], 'Geri bildirim tipleri');
    }
}


