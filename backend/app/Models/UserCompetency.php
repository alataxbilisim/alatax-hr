<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCompetency extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'competency_id',
        'current_level',
        'target_level',
        'assessed_at',
        'assessed_by',
        'notes',
    ];

    protected $casts = [
        'current_level' => 'integer',
        'target_level' => 'integer',
        'assessed_at' => 'date',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    // Methods
    public function getGap(): int
    {
        if (!$this->target_level) {
            return 0;
        }
        return $this->target_level - $this->current_level;
    }

    public function hasGap(): bool
    {
        return $this->getGap() > 0;
    }

    public function getProgressPercentage(): float
    {
        if (!$this->target_level || $this->target_level == 0) {
            return 100;
        }
        return min(100, ($this->current_level / $this->target_level) * 100);
    }
}


