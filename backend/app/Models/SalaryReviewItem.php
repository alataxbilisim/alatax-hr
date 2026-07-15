<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryReviewItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'period_id',
        'employee_id',
        'current_amount',
        'proposed_amount',
        'increase_percent',
        'currency',
        'change_reason',
        'note',
    ];

    protected $casts = [
        'current_amount' => 'decimal:2',
        'proposed_amount' => 'decimal:2',
        'increase_percent' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(SalaryReviewPeriod::class, 'period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
