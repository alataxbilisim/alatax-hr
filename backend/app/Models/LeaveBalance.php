<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'leave_type_id',
        'year',
        'total_days',
        'used_days',
        'pending_days',
        'carried_over',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'pending_days' => 'decimal:2',
        'carried_over' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    // Accessors
    public function getRemainingDaysAttribute()
    {
        return $this->total_days - $this->used_days - $this->pending_days;
    }

    // Methods
    public function canRequest(float $days): bool
    {
        return $this->remaining_days >= $days;
    }

    public function addPending(float $days): void
    {
        $this->pending_days += $days;
        $this->save();
    }

    public function approvePending(float $days): void
    {
        $this->pending_days -= $days;
        $this->used_days += $days;
        $this->save();
    }

    public function rejectPending(float $days): void
    {
        $this->pending_days -= $days;
        $this->save();
    }
}
