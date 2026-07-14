<?php

namespace App\Http\Controllers\Api\V1\Leaves;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Leaves\BulkUpdateLeaveBalanceRequest;
use App\Http\Requests\Leaves\UpdateLeaveBalanceRequest;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\DataScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveBalanceController extends BaseController
{
    public function __construct(
        private readonly DataScopeService $dataScope,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->getCompanyId();
        $actor = $request->user();

        $query = LeaveBalance::with(['user', 'leaveType'])
            ->where('company_id', $companyId);

        // DataScope + şube bağlamı (Employee global scope üzerinden)
        $allowedUserIds = $this->scopedUserIds($actor, $companyId);
        $query->whereIn('user_id', $allowedUserIds);

        if ($request->filled('user_id')) {
            $filterUserId = (int) $request->user_id;
            if (! in_array($filterUserId, $allowedUserIds, true)) {
                return $this->forbidden('Bu kullanıcının bakiyelerini görüntüleme yetkiniz yok.');
            }
            $query->where('user_id', $filterUserId);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        } else {
            $query->where('year', now()->year);
        }

        $balances = $query->paginate($request->get('per_page', 15));

        return $this->paginated($balances, 'İzin bakiyeleri listelendi');
    }

    /**
     * Get my balance.
     */
    public function myBalance(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $balances = LeaveBalance::with('leaveType')
            ->where('user_id', auth()->id())
            ->where('year', $year)
            ->get();

        $leaveTypes = LeaveType::active()->get();

        foreach ($leaveTypes as $leaveType) {
            $exists = $balances->where('leave_type_id', $leaveType->id)->first();
            if (! $exists) {
                $balance = LeaveBalance::create([
                    'company_id' => $this->getCompanyId(),
                    'user_id' => auth()->id(),
                    'leave_type_id' => $leaveType->id,
                    'year' => $year,
                    'total_days' => $leaveType->default_days,
                    'used_days' => 0,
                    'pending_days' => 0,
                ]);
                $balance->load('leaveType');
                $balances->push($balance);
            }
        }

        return $this->success($balances, 'İzin bakiyelerim listelendi');
    }

    /**
     * Update a user's balance (gerekçeli + audit).
     */
    public function update(UpdateLeaveBalanceRequest $request, LeaveBalance $leaveBalance): JsonResponse
    {
        $actor = $request->user();
        if (! $this->canManageBalance($actor, $leaveBalance)) {
            return $this->forbidden('Bu bakiyeyi güncelleme yetkiniz yok.');
        }

        $validated = $request->validated();
        $oldValues = [
            'total_days' => $leaveBalance->total_days,
            'carried_over' => $leaveBalance->carried_over,
        ];

        $leaveBalance->update([
            'total_days' => $validated['total_days'],
            'carried_over' => $validated['carried_over'] ?? $leaveBalance->carried_over,
        ]);

        ActivityLog::log(
            'leave_balance_manual_update',
            $leaveBalance,
            'Manuel izin bakiyesi: '.$leaveBalance->leaveType?->name.' — '.$validated['reason'],
            $oldValues,
            [
                'total_days' => $leaveBalance->total_days,
                'carried_over' => $leaveBalance->carried_over,
                'reason' => $validated['reason'],
            ]
        );

        return $this->success(
            $leaveBalance->fresh()->load(['user', 'leaveType']),
            'İzin bakiyesi güncellendi'
        );
    }

    /**
     * Bulk update balances for a user.
     */
    public function bulkUpdate(BulkUpdateLeaveBalanceRequest $request): JsonResponse
    {
        $actor = $request->user();
        $companyId = $this->getCompanyId();
        $validated = $request->validated();

        $targetUser = User::query()
            ->where('id', $validated['user_id'])
            ->where('company_id', $companyId)
            ->first();

        if ($targetUser === null) {
            return $this->forbidden('Kullanıcı bu firmada bulunamadı.');
        }

        if (! $this->dataScope->allowsUserId($actor, (int) $targetUser->id)
            || ! $this->userInBranchContext($targetUser->id, $companyId)) {
            return $this->forbidden('Bu kullanıcının bakiyelerini güncelleme yetkiniz yok.');
        }

        $updated = [];
        $errors = [];

        foreach ($validated['balances'] as $index => $balanceData) {
            $leaveType = LeaveType::query()
                ->where('id', $balanceData['leave_type_id'])
                ->where('company_id', $companyId)
                ->first();

            if ($leaveType === null) {
                $errors[] = [
                    'index' => $index,
                    'leave_type_id' => $balanceData['leave_type_id'],
                    'message' => 'İzin türü bu firmaya ait değil.',
                ];

                continue;
            }

            $balance = LeaveBalance::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'user_id' => $targetUser->id,
                    'leave_type_id' => $leaveType->id,
                    'year' => $validated['year'],
                ],
                [
                    'total_days' => $balanceData['total_days'],
                ]
            );

            ActivityLog::log(
                'leave_balance_bulk_update',
                $balance,
                'Toplu izin bakiyesi: '.$leaveType->name.' — '.$validated['reason'],
                null,
                [
                    'total_days' => $balance->total_days,
                    'reason' => $validated['reason'],
                    'user_id' => $targetUser->id,
                    'year' => $validated['year'],
                ]
            );

            $updated[] = $balance->id;
        }

        if ($updated === [] && $errors !== []) {
            return $this->error('Hiçbir bakiye güncellenemedi.', 422, $errors);
        }

        return $this->success([
            'updated_ids' => $updated,
            'errors' => $errors,
        ], 'İzin bakiyeleri toplu güncellendi');
    }

    private function canManageBalance(?User $actor, LeaveBalance $balance): bool
    {
        if ($actor === null) {
            return false;
        }

        if ((int) $balance->company_id !== (int) $actor->company_id) {
            return false;
        }

        if (! $this->dataScope->allowsUserId($actor, (int) $balance->user_id)) {
            return false;
        }

        return $this->userInBranchContext((int) $balance->user_id, (int) $actor->company_id);
    }

    /**
     * DataScope + branch-context (Employee scope) ile izinli user_id listesi.
     *
     * @return list<int>
     */
    private function scopedUserIds(User $actor, ?int $companyId): array
    {
        $employeeQuery = Employee::query()
            ->where('company_id', $companyId)
            ->whereNotNull('user_id');

        $userIds = $employeeQuery->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        // user_id'siz personel yok; DataScope user tablosu üzerinden de filtrele
        $userQuery = User::query()->where('company_id', $companyId)->whereIn('id', $userIds !== [] ? $userIds : [0]);
        $this->dataScope->scopeForUser($userQuery, $actor, 'id');

        return $userQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function userInBranchContext(int $userId, int $companyId): bool
    {
        // Employee global scope şube bağlamını uygular; kayıt yoksa false
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->exists();
    }
}
