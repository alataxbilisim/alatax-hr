<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegation extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'delegator_id',
        'delegate_id',
        'start_date',
        'end_date',
        'entity_type',
        'is_active',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeForDelegator($query, int $userId)
    {
        return $query->where('delegator_id', $userId);
    }

    // Methods
    public function isCurrentlyActive(): bool
    {
        return $this->is_active
            && $this->start_date <= now()
            && $this->end_date >= now();
    }

    /**
     * Aktif vekili bul
     */
    public static function findActiveDelegate(int $delegatorId, int $companyId, ?string $entityType = null): ?User
    {
        $query = self::where('company_id', $companyId)
            ->where('delegator_id', $delegatorId)
            ->active();

        if ($entityType) {
            $query->where(function ($q) use ($entityType) {
                $q->whereNull('entity_type')
                    ->orWhere('entity_type', $entityType);
            });
        }

        $delegation = $query->first();

        return $delegation?->delegate;
    }
}


