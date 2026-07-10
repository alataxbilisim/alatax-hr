<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningPath extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'thumbnail_path',
        'level',
        'estimated_hours',
        'is_mandatory',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'estimated_hours' => 'integer',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    const LEVEL_BEGINNER = 'beginner';

    const LEVEL_INTERMEDIATE = 'intermediate';

    const LEVEL_ADVANCED = 'advanced';

    public static function getLevelLabels(): array
    {
        return [
            self::LEVEL_BEGINNER => 'Başlangıç',
            self::LEVEL_INTERMEDIATE => 'Orta',
            self::LEVEL_ADVANCED => 'İleri',
        ];
    }

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(LearningPathItem::class)->orderBy('order_number');
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserLearningPath::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Methods
    public function getTrainingsCount(): int
    {
        return $this->items()->count();
    }
}
