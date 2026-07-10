<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDashboard extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'description',
        'widgets',
        'layout_config',
        'is_favorite',
        'is_shared',
        'sort_order',
    ];

    protected $casts = [
        'widgets' => 'array',
        'layout_config' => 'array',
        'is_favorite' => 'boolean',
        'is_shared' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Dashboard'u oluşturan kullanıcı
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Şirket
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Kullanıcının kendi dashboard'ları
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Paylaşılan dashboard'lar
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Favori dashboard'lar
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Kullanıcının erişebileceği dashboard'lar (kendi + paylaşılanlar)
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }

    /**
     * Varsayılan widget yapısı
     */
    public static function getDefaultWidgetStructure(): array
    {
        return [
            'id' => null,
            'type' => 'chart', // chart, kpi, table, treemap, text
            'title' => '',
            'notes' => '',
            'labels' => [],
            'config' => [
                'dimension' => 'department',
                'measure' => 'count',
                'chartType' => 'bar',
                'filters' => [],
            ],
            'layout' => [
                'x' => 0,
                'y' => 0,
                'w' => 6,
                'h' => 4,
                'minW' => 2,
                'minH' => 2,
            ],
        ];
    }
}

