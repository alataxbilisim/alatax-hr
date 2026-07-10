<?php

namespace App\Http\Controllers\Api\V1\Timesheet;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends BaseController
{
    /**
     * Attendance kayıtlarını listele
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceRecord::where('company_id', $this->getCompanyId())
            ->with(['user:id,name,email']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        } elseif ($request->has('date')) {
            $query->where('date', $request->date);
        } else {
            // Default: current month
            $query->whereMonth('date', now()->month)
                ->whereYear('date', now()->year);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $records = $query->orderBy('date', 'desc')
            ->orderBy('user_id')
            ->paginate($request->get('per_page', 50));

        return $this->paginated($records, 'Attendance kayıtları listelendi');
    }

    /**
     * Tek bir attendance kaydı
     */
    public function show(int $id): JsonResponse
    {
        $record = AttendanceRecord::where('company_id', $this->getCompanyId())
            ->with(['user:id,name,email', 'approver:id,name'])
            ->findOrFail($id);

        return $this->success($record, 'Attendance kaydı detayı');
    }

    /**
     * Yeni attendance kaydı oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'status' => 'nullable|in:present,absent,late,early_leave,holiday,leave',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['company_id'] = $this->getCompanyId();
        $validated['clock_in_method'] = 'manual';

        // Check if record already exists
        $existing = AttendanceRecord::where('company_id', $validated['company_id'])
            ->where('user_id', $validated['user_id'])
            ->where('date', $validated['date'])
            ->first();

        if ($existing) {
            return $this->error('Bu tarih için zaten kayıt mevcut', 422);
        }

        $record = AttendanceRecord::create($validated);

        // Calculate total hours
        if ($record->clock_in && $record->clock_out) {
            $record->total_hours = $record->calculateTotalHours();
            $record->save();
        }

        ActivityLog::log('attendance_created', $record, 'Attendance kaydı oluşturuldu');

        return $this->success($record->load('user:id,name'), 'Attendance kaydı oluşturuldu', 201);
    }

    /**
     * Attendance kaydı güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $record = AttendanceRecord::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $oldValues = $record->toArray();

        $validated = $request->validate([
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'status' => 'nullable|in:present,absent,late,early_leave,holiday,leave',
            'notes' => 'nullable|string|max:500',
        ]);

        $record->update($validated);

        // Recalculate total hours
        if ($record->clock_in && $record->clock_out) {
            $record->total_hours = $record->calculateTotalHours();
            $record->save();
        }

        ActivityLog::log('attendance_updated', $record, 'Attendance kaydı güncellendi', $oldValues, $record->toArray());

        return $this->success($record->load('user:id,name'), 'Attendance kaydı güncellendi');
    }

    /**
     * Attendance kaydını onayla
     */
    public function approve(int $id): JsonResponse
    {
        $record = AttendanceRecord::where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $record->update([
            'is_approved' => true,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        ActivityLog::log('attendance_approved', $record, 'Attendance kaydı onaylandı');

        return $this->success($record, 'Attendance kaydı onaylandı');
    }

    /**
     * Toplu onay
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:attendance_records,id',
        ]);

        $count = AttendanceRecord::where('company_id', $this->getCompanyId())
            ->whereIn('id', $validated['ids'])
            ->where('is_approved', false)
            ->update([
                'is_approved' => true,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

        ActivityLog::log('attendance_bulk_approved', null, "{$count} attendance kaydı onaylandı");

        return $this->success(['approved_count' => $count], "{$count} kayıt onaylandı");
    }

    /**
     * Günlük özet
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->toDateString());

        $records = AttendanceRecord::where('company_id', $this->getCompanyId())
            ->where('date', $date)
            ->get();

        $summary = [
            'date' => $date,
            'total_employees' => $records->count(),
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'on_leave' => $records->where('status', 'leave')->count(),
            'average_clock_in' => $records->whereNotNull('clock_in')->avg(function ($r) {
                return Carbon::parse($r->clock_in)->secondsSinceMidnight();
            }),
            'average_hours' => $records->avg('total_hours'),
        ];

        // Format average clock in time
        if ($summary['average_clock_in']) {
            $avgSeconds = (int) $summary['average_clock_in'];
            $summary['average_clock_in'] = sprintf('%02d:%02d', floor($avgSeconds / 3600), floor(($avgSeconds % 3600) / 60));
        }

        return $this->success($summary, 'Günlük özet');
    }
}
