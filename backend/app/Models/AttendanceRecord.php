<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'break_start',
        'break_end',
        'total_hours',
        'overtime_hours',
        'clock_in_method',
        'clock_out_method',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_in_ip',
        'clock_out_ip',
        'status',
        'notes',
        'is_approved',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime:H:i',
        'clock_out' => 'datetime:H:i',
        'break_start' => 'datetime:H:i',
        'break_end' => 'datetime:H:i',
        'total_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'clock_in_latitude' => 'decimal:7',
        'clock_in_longitude' => 'decimal:7',
        'clock_out_latitude' => 'decimal:7',
        'clock_out_longitude' => 'decimal:7',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    const STATUS_PRESENT = 'present';

    const STATUS_ABSENT = 'absent';

    const STATUS_LATE = 'late';

    const STATUS_EARLY_LEAVE = 'early_leave';

    const STATUS_HOLIDAY = 'holiday';

    const STATUS_LEAVE = 'leave';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function calculateTotalHours(): float
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return 0;
        }

        $date = $this->date instanceof \Carbon\CarbonInterface
            ? $this->date->format('Y-m-d')
            : (string) $this->date;

        $clockInTime = $this->clock_in instanceof \Carbon\CarbonInterface
            ? $this->clock_in->format('H:i')
            : (string) $this->clock_in;
        $clockOutTime = $this->clock_out instanceof \Carbon\CarbonInterface
            ? $this->clock_out->format('H:i')
            : (string) $this->clock_out;

        $clockIn = \Carbon\Carbon::parse($date.' '.$clockInTime);
        $clockOut = \Carbon\Carbon::parse($date.' '.$clockOutTime);

        // Handle overnight shifts
        if ($clockOut < $clockIn) {
            $clockOut->addDay();
        }

        $totalMinutes = $clockOut->diffInMinutes($clockIn);

        // Subtract break time if exists
        if ($this->break_start && $this->break_end) {
            $breakStartTime = $this->break_start instanceof \Carbon\CarbonInterface
                ? $this->break_start->format('H:i')
                : (string) $this->break_start;
            $breakEndTime = $this->break_end instanceof \Carbon\CarbonInterface
                ? $this->break_end->format('H:i')
                : (string) $this->break_end;

            $breakStart = \Carbon\Carbon::parse($date.' '.$breakStartTime);
            $breakEnd = \Carbon\Carbon::parse($date.' '.$breakEndTime);
            $breakMinutes = $breakEnd->diffInMinutes($breakStart);
            $totalMinutes -= $breakMinutes;
        }

        return round($totalMinutes / 60, 2);
    }
}
