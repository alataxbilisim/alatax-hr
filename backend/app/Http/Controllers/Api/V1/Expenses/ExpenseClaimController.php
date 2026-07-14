<?php

namespace App\Http\Controllers\Api\V1\Expenses;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Expenses\MarkPaidExpenseClaimRequest;
use App\Models\ActivityLog;
use App\Models\ApprovalRecord;
use App\Models\ExpenseClaim;
use App\Services\DataScopeService;
use App\Services\LookupService;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * HR / manager masraf talepleri (portal own API'sinden ayrı).
 */
class ExpenseClaimController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
        protected LookupService $lookups,
        protected WorkflowService $workflowService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExpenseClaim::class);

        $query = ExpenseClaim::with(['user:id,name,email', 'approver:id,name'])
            ->withCount('items')
            ->latest();

        $this->dataScope->scopeForUser($query, $request->user());

        if ($request->filled('status')) {
            $this->lookups->assertValid(
                LookupService::TYPE_EXPENSE_CLAIM_STATUS,
                $request->string('status')->toString(),
                $this->getCompanyId(),
                'status'
            );
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $claims = $query->paginate($request->get('per_page', 15));

        return $this->success($claims, 'Masraf talepleri listelendi');
    }

    public function show(ExpenseClaim $expenseClaim): JsonResponse
    {
        $this->authorize('view', $expenseClaim);

        return $this->success(
            $expenseClaim->load(['user:id,name,email', 'items.category:id,name', 'approver:id,name']),
            'Masraf talebi detayı'
        );
    }

    /**
     * Köprü: aktif motor kaydı varsa WorkflowService; yoksa legacy status update.
     */
    public function approve(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $this->authorize('approve', $expenseClaim);

        if ($expenseClaim->status !== ExpenseClaim::STATUS_SUBMITTED) {
            return $this->error('Sadece gönderilmiş masraf talepleri onaylanabilir', 422);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $currentRecord = $expenseClaim->approvalRecords()
            ->where('is_current', true)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->first();

        if ($currentRecord) {
            $ok = $this->workflowService->processAuthorizedApproval(
                $currentRecord,
                (int) auth()->id(),
                $validated['note'] ?? null
            );

            if (! $ok) {
                return $this->error('Onay adımı işlenemedi', 422);
            }
        } else {
            Log::warning('expense.claim.legacy_approve_without_workflow_record', [
                'expense_claim_id' => $expenseClaim->id,
                'company_id' => $expenseClaim->company_id,
                'actor_id' => auth()->id(),
            ]);

            ExpenseClaim::withoutAuditing(fn () => $expenseClaim->update([
                'status' => ExpenseClaim::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ]));
        }

        ActivityLog::log(
            'approved',
            $expenseClaim,
            'Masraf talebi onaylandı'.(isset($validated['note']) ? ': '.$validated['note'] : '')
        );

        return $this->success($expenseClaim->fresh(), 'Masraf talebi onaylandı');
    }

    public function reject(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $this->authorize('approve', $expenseClaim);

        if ($expenseClaim->status !== ExpenseClaim::STATUS_SUBMITTED) {
            return $this->error('Sadece gönderilmiş masraf talepleri reddedilebilir', 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $currentRecord = $expenseClaim->approvalRecords()
            ->where('is_current', true)
            ->where('status', ApprovalRecord::STATUS_PENDING)
            ->first();

        if ($currentRecord) {
            $ok = $this->workflowService->processAuthorizedRejection(
                $currentRecord,
                (int) auth()->id(),
                $validated['reason']
            );

            if (! $ok) {
                return $this->error('Red adımı işlenemedi', 422);
            }
        } else {
            Log::warning('expense.claim.legacy_reject_without_workflow_record', [
                'expense_claim_id' => $expenseClaim->id,
                'company_id' => $expenseClaim->company_id,
                'actor_id' => auth()->id(),
            ]);

            ExpenseClaim::withoutAuditing(fn () => $expenseClaim->update([
                'status' => ExpenseClaim::STATUS_REJECTED,
                'rejection_reason' => $validated['reason'],
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]));
        }

        ActivityLog::log('rejected', $expenseClaim, 'Masraf talebi reddedildi: '.$validated['reason']);

        return $this->success($expenseClaim->fresh(), 'Masraf talebi reddedildi');
    }

    /**
     * Onay sonrası ödeme adımı: approved → paid.
     */
    public function markPaid(MarkPaidExpenseClaimRequest $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $this->authorize('view', $expenseClaim);

        if ($expenseClaim->status !== ExpenseClaim::STATUS_APPROVED) {
            return $this->error('Sadece onaylanmış masraf talepleri ödendi olarak işaretlenebilir', 422);
        }

        $validated = $request->validated();

        ExpenseClaim::withoutAuditing(fn () => $expenseClaim->update([
            'status' => ExpenseClaim::STATUS_PAID,
            'paid_by' => auth()->id(),
            'paid_at' => now(),
            'payment_method' => $validated['payment_method'] ?? null,
            'payment_reference' => $validated['payment_reference'] ?? null,
        ]));

        ActivityLog::log(
            'paid',
            $expenseClaim,
            'Masraf talebi ödendi olarak işaretlendi'
                .(isset($validated['note']) ? ': '.$validated['note'] : '')
        );

        return $this->success($expenseClaim->fresh(), 'Masraf talebi ödendi olarak işaretlendi');
    }
}
