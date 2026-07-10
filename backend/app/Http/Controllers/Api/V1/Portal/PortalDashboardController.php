<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\LeaveRequest;
use App\Models\Payslip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalDashboardController extends BaseController
{
    /**
     * Personel dashboard verileri
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $this->getEmployee($user);
        
        if (!$employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        // İzin bakiyesi
        $leaveBalance = $this->getLeaveBalance($employee);
        
        // Bekleyen talepler
        $pendingRequests = EmployeeRequest::where('employee_id', $employee->id)
            ->pending()
            ->count();
        
        // Bekleyen izin talepleri
        $pendingLeaves = LeaveRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();
        
        // Son duyurular
        $announcements = Announcement::where('company_id', $employee->company_id)
            ->active()
            ->orderByPinned()
            ->limit(5)
            ->get(['id', 'title', 'summary', 'type', 'published_at', 'is_pinned']);

        // Son bordro
        $latestPayslip = Payslip::where('employee_id', $employee->id)
            ->published()
            ->latest('period')
            ->first(['id', 'period', 'year', 'month', 'is_viewed']);

        // Son talepler
        $recentRequests = EmployeeRequest::where('employee_id', $employee->id)
            ->with('requestType:id,name,icon')
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'status', 'priority', 'request_type_id', 'created_at']);

        return $this->success([
            'employee' => [
                'id' => $employee->id,
                'name' => $user->name,
                'position' => $employee->position,
                'department' => $employee->department?->name,
                'hire_date' => $employee->hire_date?->format('d.m.Y'),
                'seniority_years' => $employee->seniority_years,
            ],
            'stats' => [
                'leave_balance' => $leaveBalance,
                'pending_requests' => $pendingRequests,
                'pending_leaves' => $pendingLeaves,
            ],
            'announcements' => $announcements,
            'latest_payslip' => $latestPayslip,
            'recent_requests' => $recentRequests,
        ]);
    }

    /**
     * Personel kaydını getir
     */
    private function getEmployee($user): ?Employee
    {
        return Employee::where('user_id', $user->id)->first();
    }

    /**
     * İzin bakiyesi hesapla
     */
    private function getLeaveBalance(Employee $employee): array
    {
        // LeaveBalance modelinden veya hesaplayarak
        $currentYear = now()->year;
        
        $balances = \App\Models\LeaveBalance::where('user_id', $employee->user_id)
            ->where('year', $currentYear)
            ->with('leaveType:id,name')
            ->get();

        return $balances->map(function ($balance) {
            return [
                'type' => $balance->leaveType->name ?? 'Bilinmiyor',
                'total' => $balance->total_days,
                'used' => $balance->used_days,
                'remaining' => $balance->remaining_days,
            ];
        })->toArray();
    }
}

