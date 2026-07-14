<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Form Engine — entity form layout tanımı.
 * company_id null = sistem varsayılanı; firma satırı override eder.
 */
class FormDefinition extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'entity_type',
        'name',
        'is_active',
        'layout',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'layout' => 'array',
        'is_active' => 'boolean',
    ];
}
