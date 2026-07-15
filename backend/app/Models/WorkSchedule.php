<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'working_days',
        'default_start_time',
        'default_end_time',
        'break_start',
        'break_end',
        'break_duration_minutes',
        'daily_hours',
        'weekly_hours',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'working_days' => 'array',
        'default_start_time' => 'datetime:H:i',
        'default_end_time' => 'datetime:H:i',
        'break_start' => 'datetime:H:i',
        'break_end' => 'datetime:H:i',
        'break_duration_minutes' => 'integer',
        'daily_hours' => 'decimal:2',
        'weekly_hours' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
