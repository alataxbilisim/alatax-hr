<?php

namespace App\Services\Timesheet;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\EmployeeShift;
use App\Models\Shift;
use Carbon\Carbon;

/**
 * Vardiya / firma varsayılanına göre geç-erken-eksik-mesai hesabı.
 */
class AttendanceCalcService
{
    public const DEFAULT_START = '09:00';

    public const DEFAULT_END = '18:00';

    public const DEFAULT_TOLERANCE = 15;

    /**
     * Clock-out veya manuel düzeltme sonrası kaydı yeniden hesapla.
     */
    public function recalculate(AttendanceRecord $record): AttendanceRecord
    {
        $record->refresh();

        $schedule = $this->resolveSchedule(
            (int) $record->company_id,
            (int) $record->user_id,
            $this->dateString($record->date)
        );

        $lateMinutes = 0;
        $earlyMinutes = 0;
        $missingMinutes = 0;
        $overtimeHours = 0.0;
        $status = AttendanceRecord::STATUS_PRESENT;

        $clockIn = $record->clock_in ? $this->timeOnDate($record->date, $record->clock_in) : null;
        $clockOut = $record->clock_out ? $this->timeOnDate($record->date, $record->clock_out) : null;

        $start = $this->timeOnDate($record->date, $schedule['start']);
        $end = $this->timeOnDate($record->date, $schedule['end']);
        if ($schedule['is_night'] && $end->lte($start)) {
            $end->addDay();
        }

        $tolerance = $schedule['tolerance'];

        if ($clockIn) {
            if ($clockIn->gt($start)) {
                $lateRaw = (int) $start->diffInMinutes($clockIn);
                if ($lateRaw > $tolerance) {
                    $lateMinutes = $lateRaw;
                    $status = AttendanceRecord::STATUS_LATE;
                }
            }
        }

        if ($clockIn && $clockOut) {
            if ($clockOut->lt($clockIn) && $schedule['is_night']) {
                $clockOut = $clockOut->copy()->addDay();
            }

            if ($clockOut->lt($end)) {
                $earlyMinutes = (int) $clockOut->diffInMinutes($end);
                if ($status === AttendanceRecord::STATUS_PRESENT) {
                    $status = AttendanceRecord::STATUS_EARLY_LEAVE;
                }
            } elseif ($clockOut->gt($end)) {
                $overtimeMinutes = (int) $end->diffInMinutes($clockOut);
                $overtimeHours = round($overtimeMinutes / 60, 2);
            }

            $expectedMinutes = max(0, (int) $start->diffInMinutes($end) - $schedule['break_minutes']);
            $workedMinutes = (int) round($record->calculateTotalHours() * 60);
            $missingMinutes = max(0, $expectedMinutes - $workedMinutes);
        } elseif ($clockIn && ! $clockOut) {
            // Gece işi tamamlar; clock-out anında eksik 0
            $missingMinutes = 0;
        } elseif (! $clockIn && ! $clockOut) {
            if (in_array($record->status, [
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_HOLIDAY,
                AttendanceRecord::STATUS_LEAVE,
            ], true)) {
                $status = $record->status;
            }
        }

        // holiday / leave elle set ve giriş yoksa koru
        if (in_array($record->status, [
            AttendanceRecord::STATUS_HOLIDAY,
            AttendanceRecord::STATUS_LEAVE,
        ], true) && ! $clockIn) {
            $status = $record->status;
        }

        $record->forceFill([
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyMinutes,
            'missing_minutes' => $missingMinutes,
            'overtime_hours' => $overtimeHours,
            'status' => $status,
            'total_hours' => ($clockIn && $clockOut) ? $record->calculateTotalHours() : $record->total_hours,
        ])->save();

        return $record->fresh();
    }

    /**
     * Clock-out'suz kalan dünü işaretle (idempotent).
     *
     * @return int güncellenen kayıt sayısı
     */
    public function markIncompleteForDate(string $date): int
    {
        $records = AttendanceRecord::query()
            ->whereDate('date', $date)
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->get();

        $updated = 0;
        foreach ($records as $record) {
            // İkinci koşuda tekrar etme
            if ($record->status === AttendanceRecord::STATUS_ABSENT && $record->clock_out === null) {
                continue;
            }

            $schedule = $this->resolveSchedule(
                (int) $record->company_id,
                (int) $record->user_id,
                $this->dateString($record->date)
            );

            $start = $this->timeOnDate($record->date, $schedule['start']);
            $end = $this->timeOnDate($record->date, $schedule['end']);
            if ($schedule['is_night'] && $end->lte($start)) {
                $end->addDay();
            }

            $clockIn = $this->timeOnDate($record->date, $record->clock_in);
            $expectedRemaining = max(0, (int) $clockIn->diffInMinutes($end));

            $record->forceFill([
                'status' => AttendanceRecord::STATUS_ABSENT,
                'missing_minutes' => $expectedRemaining,
                'early_leave_minutes' => $expectedRemaining,
                'late_minutes' => (int) ($record->late_minutes ?? 0),
            ])->save();

            $updated++;
        }

        return $updated;
    }

    /**
     * @return array{start: string, end: string, tolerance: int, break_minutes: int, is_night: bool}
     */
    public function resolveSchedule(int $companyId, int $userId, string $date): array
    {
        $assignment = EmployeeShift::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->with('shift')
            ->first();

        if ($assignment && $assignment->shift instanceof Shift) {
            $shift = $assignment->shift;

            return [
                'start' => $this->formatHm($shift->start_time),
                'end' => $this->formatHm($shift->end_time),
                'tolerance' => $this->companyTolerance($companyId),
                'break_minutes' => (int) ($shift->break_duration_minutes ?? 0),
                'is_night' => (bool) $shift->is_night_shift,
            ];
        }

        $defaults = $this->companyDefaults($companyId);

        return [
            'start' => $defaults['start'],
            'end' => $defaults['end'],
            'tolerance' => $defaults['tolerance'],
            'break_minutes' => 60,
            'is_night' => false,
        ];
    }

    /**
     * @return array{start: string, end: string, tolerance: int}
     */
    public function companyDefaults(int $companyId): array
    {
        $company = Company::query()->find($companyId);
        $general = is_array($company?->settings) ? ($company->settings['general'] ?? []) : [];

        return [
            'start' => $this->normalizeHm($general['default_work_start'] ?? self::DEFAULT_START),
            'end' => $this->normalizeHm($general['default_work_end'] ?? self::DEFAULT_END),
            'tolerance' => max(0, (int) ($general['late_tolerance_minutes'] ?? self::DEFAULT_TOLERANCE)),
        ];
    }

    public function companyTolerance(int $companyId): int
    {
        return $this->companyDefaults($companyId)['tolerance'];
    }

    protected function dateString(mixed $date): string
    {
        if ($date instanceof Carbon) {
            return $date->toDateString();
        }

        return Carbon::parse((string) $date)->toDateString();
    }

    protected function timeOnDate(mixed $date, mixed $time): Carbon
    {
        $d = $this->dateString($date);
        $hm = $this->formatHm($time);

        return Carbon::parse($d.' '.$hm);
    }

    protected function formatHm(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        $s = (string) $value;
        if (preg_match('/^(\d{1,2}:\d{2})/', $s, $m)) {
            return strlen($m[1]) === 4 ? '0'.$m[1] : $m[1];
        }

        return self::DEFAULT_START;
    }

    protected function normalizeHm(mixed $value): string
    {
        return $this->formatHm($value);
    }
}
