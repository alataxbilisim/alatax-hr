<?php

namespace App\Models;

use App\Enums\KvkkLegalBasis;
use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataProcessingActivity extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'key',
        'name',
        'data_categories',
        'purpose',
        'legal_basis',
        'data_subject_group',
        'recipients',
        'retention_period_months',
        'transfer_abroad',
        'transfer_abroad_note',
        'security_measures',
        'is_system',
    ];

    protected $casts = [
        'data_categories' => 'array',
        'recipients' => 'array',
        'retention_period_months' => 'integer',
        'transfer_abroad' => 'boolean',
        'is_system' => 'boolean',
        'legal_basis' => KvkkLegalBasis::class,
    ];
}
