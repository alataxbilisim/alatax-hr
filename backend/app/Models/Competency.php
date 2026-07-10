<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditColumns;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'category',
        'levels',
        'max_level',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'levels' => 'array',
        'max_level' => 'integer',
        'is_active' => 'boolean',
    ];

    // Yaygın kategoriler
    const CATEGORY_TECHNICAL = 'technical';
    const CATEGORY_LEADERSHIP = 'leadership';
    const CATEGORY_COMMUNICATION = 'communication';
    const CATEGORY_PROBLEM_SOLVING = 'problem_solving';
    const CATEGORY_TEAMWORK = 'teamwork';
    const CATEGORY_INNOVATION = 'innovation';

    public static function getCategoryLabels(): array
    {
        return [
            self::CATEGORY_TECHNICAL => 'Teknik',
            self::CATEGORY_LEADERSHIP => 'Liderlik',
            self::CATEGORY_COMMUNICATION => 'İletişim',
            self::CATEGORY_PROBLEM_SOLVING => 'Problem Çözme',
            self::CATEGORY_TEAMWORK => 'Takım Çalışması',
            self::CATEGORY_INNOVATION => 'Yenilikçilik',
        ];
    }

    // Relationships
    public function positionCompetencies(): HasMany
    {
        return $this->hasMany(PositionCompetency::class);
    }

    public function userCompetencies(): HasMany
    {
        return $this->hasMany(UserCompetency::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Methods
    public function getLevelDescription(int $level): ?string
    {
        if (empty($this->levels)) {
            return null;
        }

        $levelData = collect($this->levels)->firstWhere('level', $level);
        return $levelData['description'] ?? null;
    }

    public function getLevelName(int $level): ?string
    {
        if (empty($this->levels)) {
            return "Seviye {$level}";
        }

        $levelData = collect($this->levels)->firstWhere('level', $level);
        return $levelData['name'] ?? "Seviye {$level}";
    }

    /**
     * Varsayılan 5 seviyeli yetkinlik tanımı
     */
    public static function getDefaultLevels(): array
    {
        return [
            ['level' => 1, 'name' => 'Başlangıç', 'description' => 'Temel bilgi ve beceriye sahip, süpervizyon altında çalışabilir'],
            ['level' => 2, 'name' => 'Gelişmekte', 'description' => 'Kısmen bağımsız çalışabilir, ara sıra yönlendirme gerektirir'],
            ['level' => 3, 'name' => 'Yetkin', 'description' => 'Bağımsız çalışabilir, standart durumları yönetebilir'],
            ['level' => 4, 'name' => 'İleri', 'description' => 'Karmaşık durumları yönetebilir, başkalarına rehberlik edebilir'],
            ['level' => 5, 'name' => 'Uzman', 'description' => 'Konunun uzmanı, organizasyona yön verebilir'],
        ];
    }
}


