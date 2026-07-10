<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Objective extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditColumns;

    protected $fillable = [
        'company_id',
        'performance_period_id',
        'parent_id',
        'level',
        'department_id',
        'owner_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'progress',
        'status',
        'weight',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress' => 'decimal:2',
        'weight' => 'decimal:2',
    ];

    const LEVEL_COMPANY = 'company';
    const LEVEL_DEPARTMENT = 'department';
    const LEVEL_TEAM = 'team';
    const LEVEL_INDIVIDUAL = 'individual';

    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public static function getLevelLabels(): array
    {
        return [
            self::LEVEL_COMPANY => 'Şirket',
            self::LEVEL_DEPARTMENT => 'Departman',
            self::LEVEL_TEAM => 'Takım',
            self::LEVEL_INDIVIDUAL => 'Bireysel',
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Taslak',
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_COMPLETED => 'Tamamlandı',
            self::STATUS_CANCELLED => 'İptal',
        ];
    }

    // Relationships
    public function period(): BelongsTo
    {
        return $this->belongsTo(PerformancePeriod::class, 'performance_period_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Objective::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Objective::class, 'parent_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(KeyResult::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeForOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    // Methods
    public function updateProgress(): void
    {
        $keyResults = $this->keyResults;
        
        if ($keyResults->isEmpty()) {
            return;
        }

        $totalWeight = $keyResults->sum('weight');
        $weightedProgress = 0;

        foreach ($keyResults as $kr) {
            $weightedProgress += ($kr->progress * $kr->weight);
        }

        $this->progress = $totalWeight > 0 ? $weightedProgress / $totalWeight : 0;
        $this->save();

        // Üst hedefi de güncelle
        if ($this->parent) {
            $this->parent->updateProgress();
        }
    }

    public function getStatusColor(): string
    {
        return match ($this->progress) {
            0 => 'gray',
            $this->progress < 30 => 'red',
            $this->progress < 70 => 'yellow',
            default => 'green',
        };
    }
}


