<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryRecord extends Model
{
    use Auditable, BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected array $auditMasked = [
        'amount',
    ];

    /** @var array<string, string> */
    protected array $auditMaskedLabels = [
        'amount' => 'ücret',
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'effective_date',
        'amount',
        'currency',
        'change_reason',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
