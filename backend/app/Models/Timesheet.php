<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timesheet extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'period_start',
        'period_end',
        'period_type',
        'total_hours',
        'regular_hours',
        'overtime_hours',
        'working_days',
        'absent_days',
        'late_days',
        'leave_days',
        'status',
        'employee_notes',
        'manager_notes',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_hours' => 'decimal:2',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'user_id', 'user_id')
            ->where('company_id', $this->company_id)
            ->whereBetween('date', [$this->period_start, $this->period_end]);
    }

    public function calculateFromAttendance(): void
    {
        $records = AttendanceRecord::where('company_id', $this->company_id)
            ->where('user_id', $this->user_id)
            ->whereBetween('date', [$this->period_start, $this->period_end])
            ->get();

        $this->total_hours = $records->sum('total_hours');
        $this->overtime_hours = $records->sum('overtime_hours');
        $this->regular_hours = $this->total_hours - $this->overtime_hours;
        $this->working_days = $records->where('status', AttendanceRecord::STATUS_PRESENT)->count();
        $this->absent_days = $records->where('status', AttendanceRecord::STATUS_ABSENT)->count();
        $this->late_days = $records->where('status', AttendanceRecord::STATUS_LATE)->count();
        $this->leave_days = $records->where('status', AttendanceRecord::STATUS_LEAVE)->count();
    }
}
