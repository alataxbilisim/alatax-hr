<?php

namespace App\Http\Controllers\Api\V1\Leaves;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\DataScopeService;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeaveRequestController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
        protected WorkflowService $workflowService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $query = LeaveRequest::with(['user', 'leaveType', 'approvedBy', 'rejectedBy'])
            ->latest();

        $this->dataScope->scopeForUser($query, $request->user());

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
        $this->authorize('create', LeaveRequest::class);

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

        // Onay zinciri motoru (yoksa legacy: pending + warning)
        $record = $this->workflowService->startWorkflow($leaveRequest, [
            'total_days' => $totalDays,
            'leave_type_id' => $leaveType->id,
            'requester_id' => auth()->id(),
        ]);

        if (! $record) {
            Log::warning('leave.request.created_without_workflow', [
                'leave_request_id' => $leaveRequest->id,
                'company_id' => $leaveRequest->company_id,
            ]);
        }

        return $this->success($leaveRequest->load(['leaveType', 'user']), 'İzin talebi oluşturuldu', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('view', $leaveRequest);

        $leaveRequest->load([
            'user',
            'leaveType',
            'approvedBy',
            'rejectedBy',
            'approvalRecords' => fn ($q) => $q->orderBy('step_order')->orderBy('id'),
            'approvalRecords.step',
            'approvalRecords.approver:id,name',
        ]);

        return $this->success($leaveRequest, 'İzin talebi detayları');
    }

    /**
     * Approve a leave request.
     * Köprü: aktif motor kaydı varsa WorkflowService; yoksa legacy LeaveRequest::approve.
     * Yetki kapısı her zaman Policy (motor bypass yok).
     */
    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            return $this->error('Bu talep zaten işlenmiş', null, 422);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $currentRecord = $this->workflowService->findPendingRecordForActor(
            $leaveRequest,
            (int) auth()->id()
        );

        if ($currentRecord) {
            $ok = $this->workflowService->processAuthorizedApproval(
                $currentRecord,
                (int) auth()->id(),
                $validated['note'] ?? null
            );

            if (! $ok) {
                return $this->error('Onay adımı işlenemedi', null, 422);
            }
        } else {
            Log::warning('leave.request.legacy_approve_without_workflow_record', [
                'leave_request_id' => $leaveRequest->id,
                'company_id' => $leaveRequest->company_id,
                'actor_id' => auth()->id(),
            ]);

            LeaveRequest::withoutAuditing(
                fn () => $leaveRequest->approve(auth()->id(), $validated['note'] ?? null)
            );
        }

        ActivityLog::log('approved', $leaveRequest, 'İzin talebi onaylandı: '.$leaveRequest->leaveType->name);

        return $this->success($leaveRequest->fresh(), 'İzin talebi onaylandı');
    }

    /**
     * Reject a leave request.
     */
    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('approve', $leaveRequest);

        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            return $this->error('Bu talep zaten işlenmiş', null, 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $currentRecord = $this->workflowService->findPendingRecordForActor(
            $leaveRequest,
            (int) auth()->id()
        );

        if ($currentRecord) {
            $ok = $this->workflowService->processAuthorizedRejection(
                $currentRecord,
                (int) auth()->id(),
                $validated['reason']
            );

            if (! $ok) {
                return $this->error('Red adımı işlenemedi', null, 422);
            }
        } else {
            Log::warning('leave.request.legacy_reject_without_workflow_record', [
                'leave_request_id' => $leaveRequest->id,
                'company_id' => $leaveRequest->company_id,
                'actor_id' => auth()->id(),
            ]);

            LeaveRequest::withoutAuditing(
                fn () => $leaveRequest->reject(auth()->id(), $validated['reason'])
            );
        }

        ActivityLog::log('rejected', $leaveRequest, 'İzin talebi reddedildi: '.$leaveRequest->leaveType->name.' - Sebep: '.$validated['reason']);

        return $this->success($leaveRequest->fresh(), 'İzin talebi reddedildi');
    }

    /**
     * Reddedilmiş talebi yeniden gönder — yeni onay instance.
     */
    public function resubmit(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('update', $leaveRequest);

        if ((int) $leaveRequest->user_id !== (int) auth()->id()) {
            return $this->error('Bu talebi yalnızca sahibi yeniden gönderebilir', null, 403);
        }

        if ($leaveRequest->status !== LeaveRequestStatus::Rejected) {
            return $this->error('Yalnızca reddedilmiş talepler yeniden gönderilebilir', null, 422);
        }

        LeaveRequest::withoutAuditing(fn () => $leaveRequest->prepareForResubmit());

        $record = $this->workflowService->resubmitWorkflow($leaveRequest->fresh(), [
            'total_days' => (float) $leaveRequest->total_days,
            'leave_type_id' => $leaveRequest->leave_type_id,
            'requester_id' => $leaveRequest->user_id,
        ]);

        if (! $record) {
            Log::warning('leave.request.resubmit_without_workflow', [
                'leave_request_id' => $leaveRequest->id,
                'company_id' => $leaveRequest->company_id,
            ]);
        }

        ActivityLog::log('resubmitted', $leaveRequest, 'İzin talebi yeniden gönderildi: '.$leaveRequest->leaveType->name);

        return $this->success($leaveRequest->fresh()->load(['leaveType', 'user']), 'İzin talebi yeniden gönderildi');
    }

    /**
     * Cancel a leave request (yalnızca pending — approved sonrası ayrı akış).
     */
    public function cancel(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('delete', $leaveRequest);

        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            return $this->error('Sadece bekleyen talepler iptal edilebilir', 422);
        }

        $pendingDays = (float) $leaveRequest->total_days;
        LeaveRequest::withoutAuditing(fn () => $leaveRequest->cancel());

        ActivityLog::log(
            'cancelled',
            $leaveRequest,
            'İzin talebi iptal edildi: '.$leaveRequest->leaveType?->name,
            ['status' => LeaveRequestStatus::Pending->value, 'pending_days_restored' => $pendingDays],
            ['status' => LeaveRequestStatus::Cancelled->value]
        );

        return $this->success(
            $leaveRequest->fresh()->load(['leaveType', 'user']),
            'İzin talebi iptal edildi'
        );
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
     * Get pending requests for approval — DataScope ile sınırlı (legacy serbest liste kapalı).
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $query = LeaveRequest::with(['user', 'leaveType'])
            ->pending()
            ->latest();

        $this->dataScope->scopeForUser($query, $request->user());

        $leaveRequests = $query->paginate($request->get('per_page', 15));

        return $this->success($leaveRequests, 'Onay bekleyen talepler listelendi');
    }
}
