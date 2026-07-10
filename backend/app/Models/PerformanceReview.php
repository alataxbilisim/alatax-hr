<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReview extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'period_id',
        'employee_id',
        'reviewer_id',
        'status',
        'overall_score',
        'strengths',
        'improvements',
        'goals',
        'reviewer_comments',
        'employee_comments',
        'submitted_at',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Değerlendirme dönemi
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PerformancePeriod::class, 'period_id');
    }

    /**
     * Değerlendirilen personel
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Değerlendiren (yönetici)
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Onaylayan
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Kriter puanları
     */
    public function scores(): HasMany
    {
        return $this->hasMany(PerformanceScore::class, 'review_id');
    }

    /**
     * Genel puanı hesapla
     */
    public function calculateOverallScore(): float
    {
        $scores = $this->scores()->with('criteria')->get();
        
        if ($scores->isEmpty()) {
            return 0;
        }

        $totalWeightedScore = 0;
        $totalWeight = 0;

        foreach ($scores as $score) {
            $weight = $score->criteria->weight;
            $maxScore = $score->criteria->max_score;
            
            // Normalize score to 100 scale
            $normalizedScore = ($score->score / $maxScore) * 100;
            
            $totalWeightedScore += $normalizedScore * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? round($totalWeightedScore / $totalWeight, 2) : 0;
    }

    /**
     * Duruma göre scope'lar
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}

