<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Firma unvan / pozisyon kataloğu (Personel).
 * JobPosition (recruitment) ile karıştırılmamalı.
 */
class Position extends Model
{
    use Auditable, BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected array $auditMasked = [];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'department_id',
        'sgk_occupation_code',
        'description',
        'is_active',
        'is_system',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Personel formunda serbest metin alanına yazılacak etiket.
     */
    public function displayLabel(): string
    {
        return $this->name;
    }
}
