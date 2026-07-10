<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\OneOnOneMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OneOnOneController extends BaseController
{
    /**
     * Görüşme listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = OneOnOneMeeting::where('company_id', $this->getCompanyId())
            ->with(['manager:id,name', 'employee:id,name']);

        // Sadece kendi görüşmelerini göster (yönetici veya çalışan olarak)
        if (! $this->isSuperAdmin() && ! $this->isCompanyAdmin()) {
            $userId = auth()->id();
            $query->where(function ($q) use ($userId) {
                $q->where('manager_id', $userId)
                    ->orWhere('employee_id', $userId);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('manager_id')) {
            $query->where('manager_id', $request->manager_id);
        }

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('upcoming')) {
            $query->upcoming();
        }

        $meetings = $query->orderBy('scheduled_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->paginated($meetings, 'Görüşmeler listelendi');
    }

    /**
     * Görüşme detayı
     */
    public function show(int $id): JsonResponse
    {
        $meeting = OneOnOneMeeting::where('company_id', $this->getCompanyId())
            ->with(['manager:id,name,email', 'employee:id,name,email'])
            ->findOrFail($id);

        // Yetki kontrolü
        $userId = auth()->id();
        if (! $this->isSuperAdmin() && ! $this->isCompanyAdmin() &&
            $meeting->manager_id !== $userId && $meeting->employee_id !== $userId) {
            return $this->error('Bu görüşmeye erişim yetkiniz yok', 403);
        }

        return $this->success($meeting, 'Görüşme detayı');
    }

    /**
     * Yeni görüşme planla
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:180',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url',
            'agenda' => 'nullable|string',
            'talking_points' => 'nullable|array',
        ]);

        $meeting = OneOnOneMeeting::create([
            'company_id' => $this->getCompanyId(),
            'manager_id' => auth()->id(),
            'employee_id' => $validated['employee_id'],
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'] ?? 30,
            'location' => $validated['location'] ?? null,
            'meeting_link' => $validated['meeting_link'] ?? null,
            'status' => OneOnOneMeeting::STATUS_SCHEDULED,
            'agenda' => $validated['agenda'] ?? null,
            'talking_points' => $validated['talking_points'] ?? null,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $meeting, 'Yeni 1-1 görüşme planlandı');

        return $this->created($meeting->load(['manager:id,name', 'employee:id,name']), 'Görüşme planlandı');
    }

    /**
     * Görüşme güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $meeting = OneOnOneMeeting::where('company_id', $this->getCompanyId())
            ->where('manager_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'sometimes|date',
            'duration_minutes' => 'nullable|integer|min:15|max:180',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url',
            'agenda' => 'nullable|string',
            'talking_points' => 'nullable|array',
            'notes' => 'nullable|string',
            'action_items' => 'nullable|array',
        ]);

        $oldValues = $meeting->toArray();
        $meeting->update($validated);

        ActivityLog::log('update', $meeting, 'Görüşme güncellendi', $oldValues, $meeting->toArray());

        return $this->success($meeting, 'Görüşme güncellendi');
    }

    /**
     * Görüşmeyi tamamla
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $meeting = OneOnOneMeeting::where('company_id', $this->getCompanyId())
            ->where('manager_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'notes' => 'nullable|string',
            'action_items' => 'nullable|array',
            'action_items.*.title' => 'required|string',
            'action_items.*.owner' => 'nullable|string',
            'action_items.*.due_date' => 'nullable|date',
            'mood' => 'nullable|in:very_negative,negative,neutral,positive,very_positive',
        ]);

        $meeting->complete(
            $validated['notes'] ?? null,
            $validated['action_items'] ?? null,
            $validated['mood'] ?? null
        );

        ActivityLog::log('update', $meeting, 'Görüşme tamamlandı');

        return $this->success($meeting, 'Görüşme tamamlandı');
    }

    /**
     * Görüşmeyi iptal et
     */
    public function cancel(int $id): JsonResponse
    {
        $meeting = OneOnOneMeeting::where('company_id', $this->getCompanyId())
            ->where(function ($q) {
                $q->where('manager_id', auth()->id())
                    ->orWhere('employee_id', auth()->id());
            })
            ->findOrFail($id);

        $meeting->cancel();

        ActivityLog::log('update', $meeting, 'Görüşme iptal edildi');

        return $this->success($meeting, 'Görüşme iptal edildi');
    }

    /**
     * Görüşmeyi yeniden planla
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $meeting = OneOnOneMeeting::where('company_id', $this->getCompanyId())
            ->where('manager_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        $meeting->reschedule(new \DateTime($validated['scheduled_at']));

        ActivityLog::log('update', $meeting, 'Görüşme yeniden planlandı');

        return $this->success($meeting, 'Görüşme yeniden planlandı');
    }

    /**
     * Yaklaşan görüşmelerim
     */
    public function upcoming(): JsonResponse
    {
        $userId = auth()->id();

        $meetings = OneOnOneMeeting::where('company_id', $this->getCompanyId())
            ->where(function ($q) use ($userId) {
                $q->where('manager_id', $userId)
                    ->orWhere('employee_id', $userId);
            })
            ->upcoming()
            ->limit(10)
            ->with(['manager:id,name', 'employee:id,name'])
            ->get();

        return $this->success($meetings, 'Yaklaşan görüşmeler');
    }

    /**
     * Durum ve mood etiketlerini getir
     */
    public function getLabels(): JsonResponse
    {
        return $this->success([
            'statuses' => OneOnOneMeeting::getStatusLabels(),
            'moods' => OneOnOneMeeting::getMoodLabels(),
            'mood_emojis' => OneOnOneMeeting::getMoodEmojis(),
        ], 'Etiketler');
    }
}
