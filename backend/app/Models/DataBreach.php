<?php

namespace App\Models;

use App\Enums\DataBreachSeverity;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataBreach extends Model
{
    use BelongsToCompany, HasAuditColumns, SoftDeletes;

    protected $fillable = [
        'company_id',
        'detected_at',
        'occurred_at',
        'description',
        'affected_categories',
        'affected_subject_count',
        'severity',
        'root_cause',
        'containment_actions',
        'notified_kvkk',
        'notified_kvkk_at',
        'notified_subjects',
        'notified_subjects_at',
        'notified_subjects_method',
        'status',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'occurred_at' => 'datetime',
        'affected_categories' => 'array',
        'affected_subject_count' => 'integer',
        'severity' => DataBreachSeverity::class,
        'notified_kvkk' => 'boolean',
        'notified_kvkk_at' => 'datetime',
        'notified_subjects' => 'boolean',
        'notified_subjects_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function kvkkDeadlineAt(): \Carbon\CarbonInterface
    {
        $hours = (int) \App\Services\Settings\Settings::get('legal.kvkk.breach_notify_hours.max', []);
        if ($hours < 1) {
            $hours = 72;
        }

        return $this->detected_at->copy()->addHours($hours);
    }

    public function isKvkkDeadlineOverdue(): bool
    {
        return ! $this->notified_kvkk && now()->greaterThan($this->kvkkDeadlineAt());
    }
}
