<?php

namespace App\Http\Controllers\Api\V1\Timesheet;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Services\DataScopeService;
use App\Services\Timesheet\AttendanceCalcService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
        protected AttendanceCalcService $calc,
    ) {}

    /**
     * Attendance kayıtlarını listele
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $query = AttendanceRecord::query()
            ->with(['user:id,name,email']);

        $this->dataScope->scopeForUser($query, $request->user());

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
        $record = AttendanceRecord::query()
            ->with(['user:id,name,email', 'approver:id,name'])
            ->findOrFail($id);

        $this->authorize('view', $record);

        return $this->success($record, 'Attendance kaydı detayı');
    }

    /**
     * Yeni attendance kaydı oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AttendanceRecord::class);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'status' => 'nullable|in:present,absent,late,early_leave,holiday,leave',
            'notes' => 'nullable|string|max:500',
            'reason' => 'required|string|min:3|max:500',
        ]);

        if (! $this->dataScope->allowsUserId($request->user(), (int) $validated['user_id'])) {
            return $this->error('Bu personel için kayıt oluşturma yetkiniz yok', 403);
        }

        $reason = $validated['reason'];
        unset($validated['reason']);

        $validated['company_id'] = $this->getCompanyId();
        $validated['clock_in_method'] = 'manual';
        $validated['source'] = 'manual';
        $validated['notes'] = trim(($validated['notes'] ?? '').' [düzeltme: '.$reason.']');

        $existing = AttendanceRecord::query()
            ->where('user_id', $validated['user_id'])
            ->where('date', $validated['date'])
            ->first();

        if ($existing) {
            return $this->error('Bu tarih için zaten kayıt mevcut', 422);
        }

        $record = AttendanceRecord::create($validated);
        $record = $this->calc->recalculate($record);

        ActivityLog::log(
            'attendance_created',
            $record,
            'Attendance kaydı oluşturuldu: '.$reason,
            null,
            $record->toArray()
        );

        return $this->success($record->load('user:id,name'), 'Attendance kaydı oluşturuldu', 201);
    }

    /**
     * Attendance kaydı güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $record = AttendanceRecord::query()->findOrFail($id);

        $this->authorize('update', $record);

        $oldValues = $record->toArray();

        $validated = $request->validate([
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'status' => 'nullable|in:present,absent,late,early_leave,holiday,leave',
            'notes' => 'nullable|string|max:500',
            'reason' => 'required|string|min:3|max:500',
        ]);

        $reason = $validated['reason'];
        unset($validated['reason']);

        $record->update($validated);
        $record = $this->calc->recalculate($record->fresh());

        ActivityLog::log(
            'attendance_updated',
            $record,
            'Attendance kaydı güncellendi: '.$reason,
            $oldValues,
            $record->toArray()
        );

        return $this->success($record->load('user:id,name'), 'Attendance kaydı güncellendi');
    }

    /**
     * Attendance kaydını onayla
     */
    public function approve(int $id): JsonResponse
    {
        $record = AttendanceRecord::query()->findOrFail($id);

        $this->authorize('approve', $record);

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

        $records = AttendanceRecord::query()
            ->whereIn('id', $validated['ids'])
            ->where('is_approved', false)
            ->get();

        $count = 0;
        foreach ($records as $record) {
            if (! $request->user()->can('approve', $record)) {
                continue;
            }

            $record->update([
                'is_approved' => true,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $count++;
        }

        ActivityLog::log('attendance_bulk_approved', null, "{$count} attendance kaydı onaylandı");

        return $this->success(['approved_count' => $count], "{$count} kayıt onaylandı");
    }

    /**
     * Günlük özet
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $date = $request->get('date', now()->toDateString());

        $query = AttendanceRecord::query()->where('date', $date);
        $this->dataScope->scopeForUser($query, $request->user());
        $records = $query->get();

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

        if ($summary['average_clock_in']) {
            $avgSeconds = (int) $summary['average_clock_in'];
            $summary['average_clock_in'] = sprintf('%02d:%02d', floor($avgSeconds / 3600), floor(($avgSeconds % 3600) / 60));
        }

        return $this->success($summary, 'Günlük özet');
    }
}
