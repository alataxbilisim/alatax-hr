<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'description',
        'config',
        'is_favorite',
        'is_shared',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'is_favorite' => 'boolean',
        'is_shared' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Raporu oluşturan kullanıcı
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
     * Kullanıcının kendi raporları
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Paylaşılan raporlar
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Favori raporlar
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Kullanıcının erişebileceği raporlar (kendi + paylaşılanlar)
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }
}

