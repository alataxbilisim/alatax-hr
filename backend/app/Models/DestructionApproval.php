<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DestructionApproval extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'approved_by',
        'approved_at',
        'candidate_ids',
        'dry_run_confirmed',
        'status',
        'note',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'candidate_ids' => 'array',
        'dry_run_confirmed' => 'boolean',
    ];

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(DestructionCandidate::class, 'approval_id');
    }
}
