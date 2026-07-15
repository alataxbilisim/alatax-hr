<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryBand extends Model
{
    use Auditable, BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected array $auditMasked = [
        'min_amount',
        'mid_amount',
        'max_amount',
    ];

    protected $fillable = [
        'company_id',
        'position_id',
        'min_amount',
        'mid_amount',
        'max_amount',
        'currency',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'mid_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
