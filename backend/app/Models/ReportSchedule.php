<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportSchedule extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    public const MAX_RECIPIENTS = 50;

    public const CADENCES = ['daily', 'weekly', 'monthly', 'cron'];

    public const FORMATS = ['link', 'excel', 'pdf'];

    protected $fillable = [
        'company_id',
        'report_id',
        'dashboard_id',
        'owner_id',
        'name',
        'cadence',
        'hour',
        'minute',
        'day',
        'cron_expression',
        'timezone',
        'format',
        'recipients',
        'filters',
        'only_if_data',
        'active',
        'last_run_at',
        'last_status',
        'failure_count',
        'next_run_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'filters' => 'array',
        'only_if_data' => 'boolean',
        'active' => 'boolean',
        'hour' => 'integer',
        'minute' => 'integer',
        'day' => 'integer',
        'failure_count' => 'integer',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class, 'report_id');
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class, 'dashboard_id');
    }
}
