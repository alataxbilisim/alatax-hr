<?php

namespace App\Http\Controllers\Api\V1\Leaves;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveCalendarController extends BaseController
{
    /**
     * Get calendar view data.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $query = LeaveRequest::with(['user', 'leaveType'])
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhere(function ($q2) use ($validated) {
                        $q2->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                    });
            });

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $leaves = $query->get();

        // Format for calendar
        $events = $leaves->map(function ($leave) {
            return [
                'id' => $leave->id,
                'title' => $leave->user->name.' - '.$leave->leaveType->name,
                'start' => $leave->start_date->format('Y-m-d'),
                'end' => $leave->end_date->addDay()->format('Y-m-d'), // Calendar end is exclusive
                'color' => $this->getLeaveColor($leave->leaveType->code),
                'extendedProps' => [
                    'user_id' => $leave->user_id,
                    'user_name' => $leave->user->name,
                    'leave_type' => $leave->leaveType->name,
                    'total_days' => $leave->total_days,
                    'status' => $leave->status,
                ],
            ];
        });

        return $this->success($events, 'İzin takvimi');
    }

    /**
     * Get today's leaves.
     */
    public function today(): JsonResponse
    {
        $today = Carbon::today();

        $leaves = LeaveRequest::with(['user', 'leaveType'])
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();

        return $this->success($leaves, 'Bugün izinli olanlar');
    }

    /**
     * Get upcoming leaves.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays($days);

        $leaves = LeaveRequest::with(['user', 'leaveType'])
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->orderBy('start_date')
            ->get();

        return $this->success($leaves, 'Yaklaşan izinler');
    }

    /**
     * Get leave color based on type.
     */
    private function getLeaveColor(?string $code): string
    {
        $colors = [
            'YI' => '#10b981', // Yıllık İzin - Green
            'MI' => '#f59e0b', // Mazeret İzni - Amber
            'HI' => '#ef4444', // Hastalık İzni - Red
            'EI' => '#ec4899', // Evlilik İzni - Pink
            'DI' => '#8b5cf6', // Doğum İzni - Purple
            'UI' => '#6b7280', // Ücretsiz İzin - Gray
        ];

        return $colors[$code] ?? '#3b82f6'; // Default blue
    }
}
