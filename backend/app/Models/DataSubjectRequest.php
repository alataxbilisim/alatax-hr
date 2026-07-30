<?php

namespace App\Models;

use App\Enums\DataSubjectRequestChannel;
use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectType;
use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataSubjectRequest extends Model
{
    use Auditable, BelongsToCompany, HasAuditColumns, SoftDeletes;

    protected $fillable = [
        'company_id',
        'subject_type',
        'subject_id',
        'applicant_name',
        'contact',
        'request_types',
        'description',
        'channel',
        'identity_verified',
        'verification_method',
        'verified_by',
        'verified_at',
        'status',
        'due_date',
        'responded_at',
        'response_body',
        'response_file_path',
        'response_template',
        'assigned_to',
        'rejection_reason',
        'destruction_pending',
        'destruction_scope',
        'email_verify_token',
        'email_verified_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'subject_type' => DataSubjectType::class,
        'channel' => DataSubjectRequestChannel::class,
        'status' => DataSubjectRequestStatus::class,
        'request_types' => 'array',
        'destruction_scope' => 'array',
        'identity_verified' => 'boolean',
        'destruction_pending' => 'boolean',
        'due_date' => 'date',
        'verified_at' => 'datetime',
        'responded_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function exportPackages(): HasMany
    {
        return $this->hasMany(DataSubjectExportPackage::class);
    }

    public function approvalRecords(): MorphMany
    {
        return $this->morphMany(ApprovalRecord::class, 'approvable');
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && ! in_array($this->status, [
                DataSubjectRequestStatus::Completed,
                DataSubjectRequestStatus::Rejected,
            ], true);
    }

    public function daysRemaining(): int
    {
        if (! $this->due_date) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false);
    }
}
