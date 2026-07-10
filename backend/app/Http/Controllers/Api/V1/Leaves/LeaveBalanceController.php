<?php

namespace App\Http\Controllers\Api\V1\Leaves;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveBalanceController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeaveBalance::with(['user', 'leaveType']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        } else {
            $query->where('year', now()->year);
        }

        $balances = $query->paginate($request->get('per_page', 15));

        return $this->success($balances, 'İzin bakiyeleri listelendi');
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

        // Get all leave types and create missing balances
        $leaveTypes = LeaveType::active()->get();
        
        foreach ($leaveTypes as $leaveType) {
            $exists = $balances->where('leave_type_id', $leaveType->id)->first();
            if (!$exists) {
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
     * Update a user's balance.
     */
    public function update(Request $request, LeaveBalance $leaveBalance): JsonResponse
    {
        $validated = $request->validate([
            'total_days' => 'required|numeric|min:0',
            'carried_over' => 'nullable|numeric|min:0',
        ]);

        $oldValues = $leaveBalance->getOriginal();
        $leaveBalance->update($validated);

        ActivityLog::log('update', $leaveBalance, 'İzin bakiyesi güncellendi: ' . $leaveBalance->leaveType->name, $oldValues, $leaveBalance->fresh()->toArray());

        return $this->success($leaveBalance, 'İzin bakiyesi güncellendi');
    }

    /**
     * Bulk update balances for a user.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'year' => 'required|integer',
            'balances' => 'required|array',
            'balances.*.leave_type_id' => 'required|exists:leave_types,id',
            'balances.*.total_days' => 'required|numeric|min:0',
        ]);

        foreach ($validated['balances'] as $balanceData) {
            $balance = LeaveBalance::updateOrCreate(
                [
                    'user_id' => $validated['user_id'],
                    'leave_type_id' => $balanceData['leave_type_id'],
                    'year' => $validated['year'],
                ],
                [
                    'company_id' => $this->getCompanyId(),
                    'total_days' => $balanceData['total_days'],
                ]
            );
            
            ActivityLog::log('update', $balance, 'İzin bakiyesi toplu güncellendi: ' . $balance->leaveType->name);
        }

        return $this->success(null, 'İzin bakiyeleri toplu güncellendi');
    }
}
