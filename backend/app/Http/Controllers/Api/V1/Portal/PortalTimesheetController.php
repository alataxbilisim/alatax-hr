<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\EmployeeShift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalTimesheetController extends BaseController
{
    /**
     * Giriş yap (Clock In)
     */
    public function clockIn(Request $request): JsonResponse
    {
        $user = auth()->user();
        $today = now()->toDateString();

        // Check if already clocked in today
        $existing = AttendanceRecord::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->clock_in) {
            return $this->error('Bugün zaten giriş yapmışsınız', 422);
        }

        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $data = [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'date' => $today,
            'clock_in' => now()->format('H:i'),
            'clock_in_method' => 'mobile',
            'clock_in_latitude' => $validated['latitude'] ?? null,
            'clock_in_longitude' => $validated['longitude'] ?? null,
            'clock_in_ip' => $request->ip(),
            'status' => 'present',
        ];

        if ($existing) {
            $existing->update($data);
            $record = $existing;
        } else {
            $record = AttendanceRecord::create($data);
        }

        ActivityLog::log('clock_in', $record, 'Giriş yapıldı');

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
        $user = auth()->user();
        $today = now()->toDateString();

        $record = AttendanceRecord::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (! $record || ! $record->clock_in) {
            return $this->error('Önce giriş yapmalısınız', 422);
        }

        if ($record->clock_out) {
            return $this->error('Bugün zaten çıkış yapmışsınız', 422);
        }

        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $record->update([
            'clock_out' => now()->format('H:i'),
            'clock_out_method' => 'mobile',
            'clock_out_latitude' => $validated['latitude'] ?? null,
            'clock_out_longitude' => $validated['longitude'] ?? null,
            'clock_out_ip' => $request->ip(),
        ]);

        // Calculate total hours
        $record->total_hours = $record->calculateTotalHours();
        $record->save();

        ActivityLog::log('clock_out', $record, 'Çıkış yapıldı');

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

        ActivityLog::log('break_started', $record, 'Mola başladı');

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

        ActivityLog::log('break_ended', $record, 'Mola bitti');

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

            // Calculate current working duration if clocked in but not out
            if ($status['is_clocked_in'] && ! $status['is_clocked_out']) {
                $clockIn = Carbon::parse($today.' '.$record->clock_in);
                $now = now();
                $minutes = $now->diffInMinutes($clockIn);

                // Subtract break time if on break
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

    /**
     * Haftalık attendance kayıtları
     */
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
            $record = $records->firstWhere('date', $date->toDateString());
            $weekData[] = [
                'date' => $date->toDateString(),
                'day_name' => $date->translatedFormat('l'),
                'clock_in' => $record?->clock_in,
                'clock_out' => $record?->clock_out,
                'total_hours' => $record?->total_hours,
                'status' => $record?->status ?? 'no_record',
            ];
        }

        $summary = [
            'week_start' => $startOfWeek->toDateString(),
            'week_end' => $endOfWeek->toDateString(),
            'total_hours' => $records->sum('total_hours'),
            'working_days' => $records->where('status', 'present')->count(),
            'records' => $weekData,
        ];

        return $this->success($summary, 'Haftalık kayıtlar');
    }

    /**
     * Aylık attendance kayıtları
     */
    public function monthlyRecords(Request $request): JsonResponse
    {
        $user = auth()->user();
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $records = AttendanceRecord::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderBy('date')
            ->get();

        $summary = [
            'year' => $year,
            'month' => $month,
            'month_name' => $startOfMonth->translatedFormat('F Y'),
            'total_hours' => $records->sum('total_hours'),
            'overtime_hours' => $records->sum('overtime_hours'),
            'working_days' => $records->where('status', 'present')->count(),
            'late_days' => $records->where('status', 'late')->count(),
            'absent_days' => $records->where('status', 'absent')->count(),
            'leave_days' => $records->where('status', 'leave')->count(),
            'records' => $records->map(function ($r) {
                return [
                    'id' => $r->id,
                    'date' => $r->date->format('Y-m-d'),
                    'day_name' => $r->date->translatedFormat('l'),
                    'clock_in' => $r->clock_in,
                    'clock_out' => $r->clock_out,
                    'total_hours' => $r->total_hours,
                    'status' => $r->status,
                ];
            }),
        ];

        return $this->success($summary, 'Aylık kayıtlar');
    }

    /**
     * Haftalık vardiya takvimi
     */
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
