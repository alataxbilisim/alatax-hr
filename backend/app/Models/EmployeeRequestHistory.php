<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeRequestHistory extends Model
{
    protected $table = 'employee_request_history';

    protected $fillable = [
        'employee_request_id',
        'old_status',
        'new_status',
        'comment',
        'changed_by',
    ];

    /**
     * Talep
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(EmployeeRequest::class, 'employee_request_id');
    }

    /**
     * Değiştiren
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
