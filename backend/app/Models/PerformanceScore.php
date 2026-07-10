<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'criteria_id',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    /**
     * Değerlendirme
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }

    /**
     * Kriter
     */
    public function criteria(): BelongsTo
    {
        return $this->belongsTo(PerformanceCriteria::class, 'criteria_id');
    }
}
