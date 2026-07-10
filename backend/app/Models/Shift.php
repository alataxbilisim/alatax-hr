<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'break_duration_minutes',
        'color',
        'is_night_shift',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'break_start' => 'datetime:H:i',
        'break_end' => 'datetime:H:i',
        'is_night_shift' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function getDurationHoursAttribute(): float
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        if ($this->is_night_shift && $end < $start) {
            $end->addDay();
        }

        $minutes = $end->diffInMinutes($start) - $this->break_duration_minutes;

        return round($minutes / 60, 2);
    }
}
