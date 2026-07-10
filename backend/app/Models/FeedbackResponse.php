<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'feedback_provider_id',
        'performance_criteria_id',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    // Relationships
    public function feedbackProvider(): BelongsTo
    {
        return $this->belongsTo(FeedbackProvider::class);
    }

    public function criteria(): BelongsTo
    {
        return $this->belongsTo(PerformanceCriteria::class, 'performance_criteria_id');
    }
}
