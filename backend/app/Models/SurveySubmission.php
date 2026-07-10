<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveySubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'user_id',
        'anonymous_id',
        'status',
        'started_at',
        'completed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_STARTED = 'started';

    const STATUS_COMPLETED = 'completed';

    const STATUS_ABANDONED = 'abandoned';

    // Relationships
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    // Methods
    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function getNpsScore(): ?int
    {
        $npsResponse = $this->responses()
            ->whereHas('question', fn ($q) => $q->where('question_type', 'nps'))
            ->first();

        return $npsResponse?->answer_numeric;
    }
}
