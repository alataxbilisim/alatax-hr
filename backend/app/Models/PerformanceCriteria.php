<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceCriteria extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'performance_criteria';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'weight',
        'max_score',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weight' => 'integer',
        'max_score' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Bu kritere ait puanlar
     */
    public function scores(): HasMany
    {
        return $this->hasMany(PerformanceScore::class, 'criteria_id');
    }

    /**
     * Oluşturan kullanıcı
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Aktif kriterler
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Sıralı listele
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
