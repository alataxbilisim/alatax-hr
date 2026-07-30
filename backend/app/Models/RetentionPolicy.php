<?php

namespace App\Models;

use App\Enums\RetentionStrategy;
use App\Enums\RetentionTriggerEvent;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetentionPolicy extends Model
{
    use BelongsToCompany, HasAuditColumns, SoftDeletes;

    protected $fillable = [
        'company_id',
        'data_category',
        'subject_type',
        'trigger_event',
        'retention_months',
        'strategy',
        'legal_basis_note',
        'active',
        'requires_approval',
        'is_system_draft',
        'name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'trigger_event' => RetentionTriggerEvent::class,
        'strategy' => RetentionStrategy::class,
        'retention_months' => 'integer',
        'active' => 'boolean',
        'requires_approval' => 'boolean',
        'is_system_draft' => 'boolean',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(DestructionCandidate::class);
    }
}
