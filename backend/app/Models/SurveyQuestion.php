<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'order_number',
        'question_text',
        'question_type',
        'options',
        'min_value',
        'max_value',
        'is_required',
        'category',
    ];

    protected $casts = [
        'options' => 'array',
        'min_value' => 'integer',
        'max_value' => 'integer',
        'is_required' => 'boolean',
        'order_number' => 'integer',
    ];

    const TYPE_SINGLE_CHOICE = 'single_choice';
    const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    const TYPE_RATING = 'rating';
    const TYPE_NPS = 'nps';
    const TYPE_TEXT = 'text';
    const TYPE_SCALE = 'scale';
    const TYPE_MATRIX = 'matrix';

    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_SINGLE_CHOICE => 'Tek Seçim',
            self::TYPE_MULTIPLE_CHOICE => 'Çoklu Seçim',
            self::TYPE_RATING => 'Puanlama',
            self::TYPE_NPS => 'NPS',
            self::TYPE_TEXT => 'Açık Uçlu',
            self::TYPE_SCALE => 'Ölçek',
            self::TYPE_MATRIX => 'Matris',
        ];
    }

    // Relationships
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    // Methods
    public function getAverageScore(): ?float
    {
        return $this->responses()->whereNotNull('answer_numeric')->avg('answer_numeric');
    }
}


