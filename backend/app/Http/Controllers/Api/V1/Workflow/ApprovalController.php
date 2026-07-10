<?php

namespace App\Http\Controllers\Api\V1\Workflow;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ApprovalRecord;
use App\Models\ApprovalDelegation;
use App\Models\ActivityLog;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApprovalController extends BaseController
{
    protected WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Bekleyen onaylarım
     */
    public function pendingApprovals(): JsonResponse
    {
        $approvals = $this->workflowService->getPendingApprovalsForUser(
            auth()->id(),
            $this->getCompanyId()
        );

        return $this->success($approvals, 'Bekleyen onaylar listelendi');
    }

    /**
     * Onay geçmişim (onayladıklarım/reddettiklerim)
     */
    public function myApprovalHistory(Request $request): JsonResponse
    {
        $query = ApprovalRecord::where('company_id', $this->getCompanyId())
            ->where('approver_id', auth()->id())
            ->whereIn('status', ['approved', 'rejected', 'skipped'])
            ->with(['approvable', 'step', 'workflow'])
            ->orderBy('decided_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $history = $query->paginate($request->get('per_page', 20));

        return $this->paginated($history, 'Onay geçmişi listelendi');
    }

    /**
     * Onay ver
     */
    public function approve(Request $request, int $recordId): JsonResponse
    {
        $record = ApprovalRecord::where('company_id', $this->getCompanyId())
            ->findOrFail($recordId);

        $validated = $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $result = $this->workflowService->approve(
            $record,
            auth()->id(),
            $validated['comment'] ?? null
        );

        if (!$result) {
            return $this->error('Bu kaydı onaylama yetkiniz yok', 403);
        }

        return $this->success($record->fresh(), 'Talep onaylandı');
    }

    /**
     * Reddet
     */
    public function reject(Request $request, int $recordId): JsonResponse
    {
        $record = ApprovalRecord::where('company_id', $this->getCompanyId())
            ->findOrFail($recordId);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $result = $this->workflowService->reject(
            $record,
            auth()->id(),
            $validated['reason']
        );

        if (!$result) {
            return $this->error('Bu kaydı reddetme yetkiniz yok', 403);
        }

        return $this->success($record->fresh(), 'Talep reddedildi');
    }

    /**
     * Adımı atla
     */
    public function skip(Request $request, int $recordId): JsonResponse
    {
        $record = ApprovalRecord::where('company_id', $this->getCompanyId())
            ->findOrFail($recordId);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $result = $this->workflowService->skip(
            $record,
            auth()->id(),
            $validated['reason'] ?? null
        );

        if (!$result) {
            return $this->error('Bu adım atlanamaz', 400);
        }

        return $this->success($record->fresh(), 'Adım atlandı');
    }

    /**
     * Vekalet listesi
     */
    public function delegations(): JsonResponse
    {
        $delegations = ApprovalDelegation::where('company_id', $this->getCompanyId())
            ->with(['delegator', 'delegate'])
            ->orderBy('start_date', 'desc')
            ->get();

        return $this->success($delegations, 'Vekaletler listelendi');
    }

    /**
     * Benim vekaletlerim
     */
    public function myDelegations(): JsonResponse
    {
        $delegations = ApprovalDelegation::where('company_id', $this->getCompanyId())
            ->where('delegator_id', auth()->id())
            ->with(['delegate'])
            ->orderBy('start_date', 'desc')
            ->get();

        return $this->success($delegations, 'Vekaletlerim listelendi');
    }

    /**
     * Vekalet oluştur
     */
    public function createDelegation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delegate_id' => 'required|exists:users,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'entity_type' => 'nullable|string',
            'reason' => 'nullable|string|max:500',
        ]);

        // Aynı tarih aralığında çakışma kontrolü
        $conflict = ApprovalDelegation::where('company_id', $this->getCompanyId())
            ->where('delegator_id', auth()->id())
            ->where('is_active', true)
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']]);
            })
            ->exists();

        if ($conflict) {
            return $this->error('Bu tarih aralığında zaten bir vekalet tanımlı', 400);
        }

        $delegation = ApprovalDelegation::create([
            'company_id' => $this->getCompanyId(),
            'delegator_id' => auth()->id(),
            'delegate_id' => $validated['delegate_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'entity_type' => $validated['entity_type'] ?? null,
            'is_active' => true,
            'reason' => $validated['reason'] ?? null,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $delegation, 'Yeni vekalet oluşturuldu');

        return $this->created($delegation->load('delegate'), 'Vekalet oluşturuldu');
    }

    /**
     * Vekalet iptal
     */
    public function cancelDelegation(int $id): JsonResponse
    {
        $delegation = ApprovalDelegation::where('company_id', $this->getCompanyId())
            ->where('delegator_id', auth()->id())
            ->findOrFail($id);

        $delegation->update(['is_active' => false]);

        ActivityLog::log('update', $delegation, 'Vekalet iptal edildi');

        return $this->success($delegation, 'Vekalet iptal edildi');
    }

    /**
     * Bir talebin onay geçmişi
     */
    public function getApprovalHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'approvable_type' => 'required|string',
            'approvable_id' => 'required|integer',
        ]);

        $history = ApprovalRecord::where('company_id', $this->getCompanyId())
            ->where('approvable_type', $validated['approvable_type'])
            ->where('approvable_id', $validated['approvable_id'])
            ->with(['step', 'approver'])
            ->orderBy('step_order')
            ->get();

        return $this->success($history, 'Onay geçmişi');
    }
}


