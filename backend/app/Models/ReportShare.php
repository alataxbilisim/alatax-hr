<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportShare extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'saved_report_id',
        'company_id',
        'user_id',
        'role_id',
        'department_id',
        'level',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class, 'saved_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
