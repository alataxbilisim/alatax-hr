<?php

namespace App\Http\Controllers\Api\V1\Expenses;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\ExpenseClaim;
use App\Services\DataScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HR / manager masraf talepleri (portal own API'sinden ayrı).
 */
class ExpenseClaimController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExpenseClaim::class);

        $query = ExpenseClaim::with(['user:id,name,email', 'approver:id,name'])
            ->withCount('items')
            ->latest();

        $this->dataScope->scopeForUser($query, $request->user());

        if ($request->has('status')) {
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

    public function approve(Request $request, ExpenseClaim $expenseClaim): JsonResponse
    {
        $this->authorize('approve', $expenseClaim);

        if ($expenseClaim->status !== ExpenseClaim::STATUS_SUBMITTED) {
            return $this->error('Sadece gönderilmiş masraf talepleri onaylanabilir', 422);
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        ExpenseClaim::withoutAuditing(fn () => $expenseClaim->update([
            'status' => ExpenseClaim::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]));

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

        ExpenseClaim::withoutAuditing(fn () => $expenseClaim->update([
            'status' => ExpenseClaim::STATUS_REJECTED,
            'rejection_reason' => $validated['reason'],
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]));

        ActivityLog::log('rejected', $expenseClaim, 'Masraf talebi reddedildi: '.$validated['reason']);

        return $this->success($expenseClaim->fresh(), 'Masraf talebi reddedildi');
    }
}
