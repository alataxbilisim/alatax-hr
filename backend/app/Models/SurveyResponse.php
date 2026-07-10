<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_submission_id',
        'survey_question_id',
        'answer_text',
        'answer_numeric',
        'answer_array',
    ];

    protected $casts = [
        'answer_numeric' => 'integer',
        'answer_array' => 'array',
    ];

    // Relationships
    public function submission(): BelongsTo
    {
        return $this->belongsTo(SurveySubmission::class, 'survey_submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}


