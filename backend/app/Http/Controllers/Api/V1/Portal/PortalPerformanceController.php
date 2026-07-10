<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\PerformanceScore;
use App\Models\Objective;
use App\Models\KeyResult;
use App\Models\ContinuousFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalPerformanceController extends BaseController
{
    /**
     * Çalışanın performans değerlendirmelerini listele
     */
    public function reviews(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $query = PerformanceReview::where('company_id', $user->company_id)
            ->where('employee_id', $employee->user_id)
            ->with([
                'period:id,name,start_date,end_date,status',
                'reviewer:id,name,email',
                'approvedBy:id,name'
            ]);

        // Durum filtresi
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Dönem filtresi
        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }

        $reviews = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginated($reviews, 'Performans değerlendirmeleri listelendi');
    }

    /**
     * Performans değerlendirme detayı
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $review = PerformanceReview::where('company_id', $user->company_id)
            ->where('employee_id', $employee->user_id)
            ->where('id', $id)
            ->with([
                'period:id,name,start_date,end_date,status,description',
                'reviewer:id,name,email',
                'approvedBy:id,name',
                'scores.criteria:id,name,description,weight,max_score'
            ])
            ->first();

        if (!$review) {
            return $this->error('Performans değerlendirmesi bulunamadı', null, 404);
        }

        return $this->success($review);
    }

    /**
     * Çalışan yorumu ekle/güncelle
     */
    public function addComment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $review = PerformanceReview::where('company_id', $user->company_id)
            ->where('employee_id', $employee->user_id)
            ->where('id', $id)
            ->whereIn('status', ['submitted', 'approved']) // Sadece gönderilmiş veya onaylanmış değerlendirmelere yorum yapılabilir
            ->first();

        if (!$review) {
            return $this->error('Performans değerlendirmesi bulunamadı veya yorum eklenemez', null, 404);
        }

        $validated = $request->validate([
            'employee_comments' => 'required|string|max:2000',
        ]);

        $review->update(['employee_comments' => $validated['employee_comments']]);

        return $this->success($review, 'Yorumunuz kaydedildi');
    }

    /**
     * Çalışanın OKR hedeflerini listele
     */
    public function okrs(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Objective::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->with(['keyResults:id,objective_id,title,current_value,target_value,unit,status']);

        // Durum filtresi
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Aktif hedefler
        if ($request->boolean('active_only')) {
            $query->where('status', 'active');
        }

        $objectives = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginated($objectives, 'Hedefler listelendi');
    }

    /**
     * OKR hedef detayı
     */
    public function okr(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $objective = Objective::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->with(['keyResults'])
            ->first();

        if (!$objective) {
            return $this->error('Hedef bulunamadı', null, 404);
        }

        return $this->success($objective);
    }

    /**
     * Key Result ilerlemesini güncelle
     */
    public function updateKeyResult(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $keyResult = KeyResult::where('id', $id)
            ->whereHas('objective', function ($q) use ($user) {
                $q->where('company_id', $user->company_id)
                    ->where('user_id', $user->id);
            })
            ->first();

        if (!$keyResult) {
            return $this->error('Anahtar sonuç bulunamadı', null, 404);
        }

        $validated = $request->validate([
            'current_value' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $keyResult->update([
            'current_value' => $validated['current_value'],
        ]);

        // İlerleme kaydı ekle
        \App\Models\KeyResultUpdate::create([
            'key_result_id' => $keyResult->id,
            'previous_value' => $keyResult->getOriginal('current_value'),
            'new_value' => $validated['current_value'],
            'note' => $validated['note'] ?? null,
            'updated_by' => $user->id,
        ]);

        // Status'u güncelle (0-25: not_started, 26-75: in_progress, 76-100: completed)
        $progress = ($validated['current_value'] / $keyResult->target_value) * 100;
        if ($progress >= 100) {
            $keyResult->update(['status' => 'completed']);
        } elseif ($progress > 0) {
            $keyResult->update(['status' => 'in_progress']);
        }

        return $this->success($keyResult->fresh(), 'İlerleme güncellendi');
    }

    /**
     * Sürekli geri bildirimleri listele
     */
    public function feedbacks(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ContinuousFeedback::where('company_id', $user->company_id)
            ->where(function ($q) use ($user) {
                $q->where('employee_id', $user->id) // Kendisine verilen geri bildirimler
                    ->orWhere('given_by_id', $user->id); // Kendisinin verdiği geri bildirimler
            })
            ->with([
                'employee:id,name,email',
                'givenBy:id,name,email'
            ]);

        // Filtreleme
        if ($request->boolean('received_only')) {
            $query->where('employee_id', $user->id);
        }

        if ($request->boolean('given_only')) {
            $query->where('given_by_id', $user->id);
        }

        $feedbacks = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginated($feedbacks, 'Geri bildirimler listelendi');
    }

    /**
     * Yeni geri bildirim ver
     */
    public function giveFeedback(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'type' => 'required|in:positive,constructive,development',
            'content' => 'required|string|max:1000',
            'is_anonymous' => 'boolean',
        ]);

        // Aynı firmada mı?
        $targetEmployee = \App\Models\User::where('id', $validated['employee_id'])
            ->where('company_id', $user->company_id)
            ->first();

        if (!$targetEmployee) {
            return $this->error('Geçersiz kullanıcı', null, 422);
        }

        $feedback = ContinuousFeedback::create([
            'company_id' => $user->company_id,
            'employee_id' => $validated['employee_id'],
            'given_by_id' => $validated['is_anonymous'] ? null : $user->id,
            'type' => $validated['type'],
            'content' => $validated['content'],
            'is_anonymous' => $validated['is_anonymous'] ?? false,
        ]);

        \App\Models\ActivityLog::log('create', $feedback, 'Geri bildirim gönderildi');

        return $this->created($feedback, 'Geri bildirim gönderildi');
    }
}

