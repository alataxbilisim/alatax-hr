<?php

namespace App\Http\Controllers\Api\V1\Leaves;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaveRequestController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['user', 'leaveType', 'approvedBy', 'rejectedBy'])
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }

        $leaveRequests = $query->paginate($request->get('per_page', 15));

        return $this->success($leaveRequests, 'İzin talepleri listelendi');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'document' => 'nullable|file|max:5120', // Max 5MB
        ]);

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);

        // Calculate total days (excluding weekends)
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $totalDays = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (! $date->isWeekend()) {
                $totalDays++;
            }
        }

        // Check balance
        $balance = LeaveBalance::firstOrCreate(
            [
                'user_id' => auth()->id(),
                'leave_type_id' => $leaveType->id,
                'year' => $startDate->year,
            ],
            [
                'company_id' => $this->getCompanyId(),
                'total_days' => $leaveType->default_days,
                'used_days' => 0,
                'pending_days' => 0,
            ]
        );

        if (! $balance->canRequest($totalDays)) {
            return $this->error('Yetersiz izin bakiyesi', null, 422);
        }

        // Handle document upload
        $documentPath = null;
        $documentName = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leave-documents/'.$this->getCompanyId(), 'public');
            $documentName = $request->file('document')->getClientOriginalName();
        }

        $leaveRequest = LeaveRequest::create([
            'company_id' => $this->getCompanyId(),
            'user_id' => auth()->id(),
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'reason' => $validated['reason'] ?? null,
            'document_path' => $documentPath,
            'document_name' => $documentName,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Update balance pending
        $balance->addPending($totalDays);

        ActivityLog::log('create', $leaveRequest, 'İzin talebi oluşturuldu: '.$leaveRequest->leaveType->name.' - '.$totalDays.' gün');

        return $this->success($leaveRequest->load(['leaveType', 'user']), 'İzin talebi oluşturuldu', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->success(
            $leaveRequest->load(['user', 'leaveType', 'approvedBy', 'rejectedBy']),
            'İzin talebi detayları'
        );
    }

    /**
     * Approve a leave request.
     */
    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            return $this->error('Bu talep zaten işlenmiş', null, 422);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $leaveRequest->approve(auth()->id(), $validated['note'] ?? null);

        ActivityLog::log('approved', $leaveRequest, 'İzin talebi onaylandı: '.$leaveRequest->leaveType->name);

        return $this->success($leaveRequest->fresh(), 'İzin talebi onaylandı');
    }

    /**
     * Reject a leave request.
     */
    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            return $this->error('Bu talep zaten işlenmiş', null, 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $leaveRequest->reject(auth()->id(), $validated['reason']);

        ActivityLog::log('rejected', $leaveRequest, 'İzin talebi reddedildi: '.$leaveRequest->leaveType->name.' - Sebep: '.$validated['reason']);

        return $this->success($leaveRequest->fresh(), 'İzin talebi reddedildi');
    }

    /**
     * Cancel a leave request.
     */
    public function cancel(LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->user_id !== auth()->id()) {
            return $this->error('Bu talebi iptal etme yetkiniz yok', null, 403);
        }

        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING) {
            return $this->error('Sadece bekleyen talepler iptal edilebilir', null, 422);
        }

        $leaveRequest->cancel();

        ActivityLog::log('cancelled', $leaveRequest, 'İzin talebi iptal edildi: '.$leaveRequest->leaveType->name);

        return $this->success($leaveRequest->fresh(), 'İzin talebi iptal edildi');
    }

    /**
     * Get my leave requests.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $leaveRequests = LeaveRequest::with(['leaveType'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate($request->get('per_page', 15));

        return $this->success($leaveRequests, 'İzin taleplerim listelendi');
    }

    /**
     * Get pending requests for approval.
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $leaveRequests = LeaveRequest::with(['user', 'leaveType'])
            ->pending()
            ->latest()
            ->paginate($request->get('per_page', 15));

        return $this->success($leaveRequests, 'Onay bekleyen talepler listelendi');
    }
}
