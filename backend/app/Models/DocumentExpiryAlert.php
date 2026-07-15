<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Süreli evrak uyarı idempotency kaydı (eşik başına bir kez).
 */
class DocumentExpiryAlert extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'employee_document_id',
        'threshold_days',
        'notified_at',
    ];

    protected $casts = [
        'threshold_days' => 'integer',
        'notified_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'employee_document_id');
    }
}
