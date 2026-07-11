<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalLeaveController extends BaseController
{
    /**
     * İzin türlerini listele
     */
    public function types(Request $request): JsonResponse
    {
        $user = $request->user();

        $leaveTypes = LeaveType::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->get(['id', 'name', 'description', 'unit', 'default_limit', 'is_paid', 'requires_document']);

        return $this->success($leaveTypes);
    }

    /**
     * İzin bakiyelerini getir
     */
    public function balances(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = $request->get('year', now()->year);

        $balances = LeaveBalance::where('user_id', $user->id)
            ->where('year', $year)
            ->with('leaveType:id,name,unit')
            ->get();

        return $this->success($balances);
    }

    /**
     * İzin taleplerini listele
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = LeaveRequest::where('user_id', $user->id)
            ->with('leaveType:id,name')
            ->orderByDesc('created_at');

        // Durum filtresi
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Yıl filtresi
        if ($request->has('year')) {
            $query->whereYear('start_date', $request->year);
        }

        $leaveRequests = $query->paginate($request->get('per_page', 15));

        return $this->paginated($leaveRequests);
    }

    /**
     * İzin talebi detayı
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $leaveRequest = LeaveRequest::where('user_id', $user->id)
            ->where('id', $id)
            ->with(['leaveType:id,name,unit', 'approver:id,name'])
            ->first();

        if (! $leaveRequest) {
            return $this->error('İzin talebi bulunamadı', null, 404);
        }

        return $this->success($leaveRequest);
    }

    /**
     * Yeni izin talebi oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'document' => 'nullable|file|max:5120', // Max 5MB
        ]);

        // İzin türünü kontrol et
        $leaveType = LeaveType::where('id', $validated['leave_type_id'])
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->first();

        if (! $leaveType) {
            return $this->error('Geçersiz izin türü', null, 422);
        }

        // Belge zorunlu mu?
        if ($leaveType->requires_document && ! $request->hasFile('document')) {
            return $this->error('Bu izin türü için belge zorunludur', null, 422);
        }

        // Gün sayısını hesapla
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        // Hafta sonlarını çıkar (opsiyonel)
        // $totalDays = $startDate->diffInWeekdays($endDate) + 1;

        return DB::transaction(function () use ($request, $validated, $user, $totalDays) {
            $documentPath = null;
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store('leave_documents/'.$user->company_id, 'public');
            }

            $leaveRequest = LeaveRequest::withoutAuditing(function () use ($validated, $user, $totalDays, $documentPath) {
                return LeaveRequest::create([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'leave_type_id' => $validated['leave_type_id'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'total_days' => $totalDays,
                    'reason' => $validated['reason'] ?? null,
                    'document_path' => $documentPath,
                    'status' => 'pending',
                ]);
            });

            ActivityLog::log('leave_requested', $leaveRequest, 'İzin talebi oluşturuldu: '.$leaveRequest->leaveType->name.' - '.$totalDays.' gün');

            return $this->created($leaveRequest, 'İzin talebi başarıyla oluşturuldu');
        });
    }

    /**
     * İzin talebini güncelle (sadece beklemede olanlar)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $leaveRequest = LeaveRequest::where('user_id', $user->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->first();

        if (! $leaveRequest) {
            return $this->error('İzin talebi bulunamadı veya düzenlenemez', null, 404);
        }

        $validated = $request->validate([
            'start_date' => 'sometimes|required|date|after_or_equal:today',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'reason' => 'sometimes|nullable|string|max:500',
            'document' => 'sometimes|nullable|file|max:5120',
        ]);

        // Gün sayısını yeniden hesapla
        $startDate = isset($validated['start_date'])
            ? \Carbon\Carbon::parse($validated['start_date'])
            : $leaveRequest->start_date;
        $endDate = isset($validated['end_date'])
            ? \Carbon\Carbon::parse($validated['end_date'])
            : $leaveRequest->end_date;
        $validated['total_days'] = $startDate->diffInDays($endDate) + 1;

        // Belge güncelleme
        if ($request->hasFile('document')) {
            if ($leaveRequest->document_path) {
                \Storage::disk('public')->delete($leaveRequest->document_path);
            }
            $validated['document_path'] = $request->file('document')->store('leave_documents/'.$user->company_id, 'public');
        }

        $leaveRequest->update($validated);

        return $this->success($leaveRequest, 'İzin talebi güncellendi');
    }

    /**
     * İzin talebini iptal et
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $leaveRequest = LeaveRequest::where('user_id', $user->id)
            ->where('id', $id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if (! $leaveRequest) {
            return $this->error('İzin talebi bulunamadı veya iptal edilemez', null, 404);
        }

        // Geçmiş tarihli izinler iptal edilemez
        if ($leaveRequest->start_date->isPast()) {
            return $this->error('Geçmiş tarihli izinler iptal edilemez', null, 422);
        }

        LeaveRequest::withoutAuditing(fn () => $leaveRequest->update(['status' => 'cancelled']));

        ActivityLog::log('leave_cancelled', $leaveRequest, 'İzin talebi iptal edildi');

        return $this->success(null, 'İzin talebi iptal edildi');
    }
}
