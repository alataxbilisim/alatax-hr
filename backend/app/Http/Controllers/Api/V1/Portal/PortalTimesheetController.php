<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\AttendanceRecord;
use App\Models\EmployeeShift;
use App\Services\Timesheet\AttendanceClockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PortalTimesheetController extends BaseController
{
    public function __construct(
        protected AttendanceClockService $clock,
    ) {}

    /**
     * Giriş yap (Clock In)
     */
    public function clockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $result = $this->clock->clockIn($request->user(), [
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'ip' => $request->ip(),
                'method' => 'mobile',
                'source' => AttendanceClockService::SOURCE_PORTAL,
                'device_info' => $request->userAgent(),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $record = $result['record'];

        return $this->success([
            'clock_in' => $record->clock_in,
            'date' => $record->date->format('Y-m-d'),
            'message' => 'Giriş başarılı',
        ], 'Giriş yapıldı');
    }

    /**
     * Çıkış yap (Clock Out)
     */
    public function clockOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $result = $this->clock->clockOut($request->user(), [
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'ip' => $request->ip(),
                'method' => 'mobile',
                'source' => AttendanceClockService::SOURCE_PORTAL,
                'device_info' => $request->userAgent(),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $record = $result['record'];

        return $this->success([
            'clock_in' => $record->clock_in,
            'clock_out' => $record->clock_out,
            'total_hours' => $record->total_hours,
            'date' => $record->date->format('Y-m-d'),
            'message' => 'Çıkış başarılı',
        ], 'Çıkış yapıldı');
    }

    /**
     * Mola başlat
     */
    public function startBreak(): JsonResponse
    {
        $user = auth()->user();
        $today = now()->toDateString();

        $record = AttendanceRecord::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (! $record || ! $record->clock_in) {
            return $this->error('Önce giriş yapmalısınız', 422);
        }

        if ($record->break_start && ! $record->break_end) {
            return $this->error('Zaten molada olduğunuz görünüyor', 422);
        }

        $record->update([
            'break_start' => now()->format('H:i'),
            'break_end' => null,
        ]);

        \App\Models\ActivityLog::log('break_started', $record, 'Mola başladı');

        return $this->success([
            'break_start' => $record->break_start,
        ], 'Mola başladı');
    }

    /**
     * Mola bitir
     */
    public function endBreak(): JsonResponse
    {
        $user = auth()->user();
        $today = now()->toDateString();

        $record = AttendanceRecord::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (! $record || ! $record->break_start) {
            return $this->error('Aktif mola bulunamadı', 422);
        }

        if ($record->break_end) {
            return $this->error('Mola zaten bitmiş', 422);
        }

        $record->update([
            'break_end' => now()->format('H:i'),
        ]);

        \App\Models\ActivityLog::log('break_ended', $record, 'Mola bitti');

        return $this->success([
            'break_start' => $record->break_start,
            'break_end' => $record->break_end,
        ], 'Mola bitti');
    }

    /**
     * Bugünkü durum
     */
    public function todayStatus(): JsonResponse
    {
        $user = auth()->user();
        $today = now()->toDateString();

        $record = AttendanceRecord::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        $status = [
            'date' => $today,
            'is_clocked_in' => false,
            'is_clocked_out' => false,
            'is_on_break' => false,
            'clock_in' => null,
            'clock_out' => null,
            'break_start' => null,
            'break_end' => null,
            'total_hours' => null,
            'working_duration' => null,
        ];

        if ($record) {
            $status['is_clocked_in'] = ! empty($record->clock_in);
            $status['is_clocked_out'] = ! empty($record->clock_out);
            $status['is_on_break'] = ! empty($record->break_start) && empty($record->break_end);
            $status['clock_in'] = $record->clock_in;
            $status['clock_out'] = $record->clock_out;
            $status['break_start'] = $record->break_start;
            $status['break_end'] = $record->break_end;
            $status['total_hours'] = $record->total_hours;

            if ($status['is_clocked_in'] && ! $status['is_clocked_out']) {
                $clockIn = Carbon::parse($today.' '.$record->clock_in);
                $now = now();
                $minutes = $now->diffInMinutes($clockIn);

                if ($status['is_on_break']) {
                    $breakStart = Carbon::parse($today.' '.$record->break_start);
                    $minutes -= $now->diffInMinutes($breakStart);
                }

                $hours = floor($minutes / 60);
                $mins = $minutes % 60;
                $status['working_duration'] = sprintf('%d saat %d dakika', $hours, $mins);
            }
        }

        return $this->success($status, 'Bugünkü durum');
    }

    public function weeklyRecords(Request $request): JsonResponse
    {
        $user = auth()->user();
        $startOfWeek = Carbon::parse($request->get('week_start', now()->startOfWeek()))->startOfWeek();
        $endOfWeek = $startOfWeek->copy()->endOfWeek();

        $records = AttendanceRecord::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->orderBy('date')
            ->get();

        $weekData = [];
        for ($date = $startOfWeek->copy(); $date <= $endOfWeek; $date->addDay()) {
            $record = $records->first(function (AttendanceRecord $r) use ($date): bool {
                return $r->date->toDateString() === $date->toDateString();
            });
            $weekData[] = [
                'date' => $date->toDateString(),
                'day_name' => $date->translatedFormat('l'),
                'clock_in' => $record?->clock_in,
                'clock_out' => $record?->clock_out,
                'total_hours' => $record?->total_hours,
                'status' => $record?->status ?? 'no_record',
            ];
        }

        return $this->success([
            'week_start' => $startOfWeek->toDateString(),
            'week_end' => $endOfWeek->toDateString(),
            'total_hours' => $records->sum('total_hours'),
            'working_days' => $records->where('status', 'present')->count(),
            'records' => $weekData,
        ], 'Haftalık kayıtlar');
    }

    public function monthlyRecords(Request $request): JsonResponse
    {
        $user = auth()->user();
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $records = AttendanceRecord::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderBy('date')
            ->get();

        return $this->success([
            'year' => $year,
            'month' => $month,
            'month_name' => $startOfMonth->translatedFormat('F Y'),
            'total_hours' => $records->sum('total_hours'),
            'overtime_hours' => $records->sum('overtime_hours'),
            'working_days' => $records->whereNotNull('clock_in')->count(),
            'late_days' => $records->where('status', 'late')->count(),
            'records' => $records->map(function (AttendanceRecord $r) {
                return [
                    'date' => $r->date->toDateString(),
                    'day_name' => $r->date->translatedFormat('l'),
                    'clock_in' => $r->clock_in,
                    'clock_out' => $r->clock_out,
                    'total_hours' => $r->total_hours,
                    'status' => $r->status,
                ];
            }),
        ], 'Aylık kayıtlar');
    }

    public function shifts(Request $request): JsonResponse
    {
        $user = auth()->user();
        $startOfWeek = Carbon::parse($request->get('week_start', now()->startOfWeek()))->startOfWeek();
        $endOfWeek = $startOfWeek->copy()->endOfWeek();

        $shifts = EmployeeShift::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->with('shift:id,name,start_time,end_time,color')
            ->orderBy('date')
            ->get();

        $weekData = [];
        for ($date = $startOfWeek->copy(); $date <= $endOfWeek; $date->addDay()) {
            $shift = $shifts->firstWhere('date', $date->toDateString());
            $weekData[] = [
                'date' => $date->toDateString(),
                'day_name' => $date->translatedFormat('l'),
                'shift' => $shift?->shift,
                'notes' => $shift?->notes,
            ];
        }

        return $this->success([
            'week_start' => $startOfWeek->toDateString(),
            'week_end' => $endOfWeek->toDateString(),
            'shifts' => $weekData,
        ], 'Vardiya takvimi');
    }
}
