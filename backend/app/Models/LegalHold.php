<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalHold extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'subject_type',
        'subject_id',
        'reason',
        'case_reference',
        'placed_by',
        'placed_at',
        'released_at',
        'released_by',
        'active',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'placed_at' => 'datetime',
        'released_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function placedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }
}
