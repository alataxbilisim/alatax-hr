<?php

namespace App\Models;

use App\Enums\GenderRestriction;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'is_paid',
        'default_days',
        'requires_document',
        'gender_restriction',
        'is_active',
        'max_days_at_once',
        'min_days_notice',
        'approval_flow',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_document' => 'boolean',
        'is_active' => 'boolean',
        'approval_flow' => 'array',
        'gender_restriction' => GenderRestriction::class,
    ];

    // Relationships
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
