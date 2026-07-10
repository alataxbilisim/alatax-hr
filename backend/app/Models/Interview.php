<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Interview extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'job_application_id',
        'job_position_id',
        'title',
        'type',
        'scheduled_at',
        'duration_minutes',
        'location',
        'meeting_link',
        'status',
        'notes',
        'overall_rating',
        'recommendation',
        'feedback',
        'interviewer_id',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'overall_rating' => 'integer',
    ];

    const TYPE_PHONE = 'phone';

    const TYPE_VIDEO = 'video';

    const TYPE_ONSITE = 'onsite';

    const TYPE_TECHNICAL = 'technical';

    const TYPE_HR = 'hr';

    const TYPE_PANEL = 'panel';

    const STATUS_SCHEDULED = 'scheduled';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_NO_SHOW = 'no_show';

    const STATUS_RESCHEDULED = 'rescheduled';

    const RECOMMENDATION_STRONG_HIRE = 'strong_hire';

    const RECOMMENDATION_HIRE = 'hire';

    const RECOMMENDATION_NO_DECISION = 'no_decision';

    const RECOMMENDATION_NO_HIRE = 'no_hire';

    const RECOMMENDATION_STRONG_NO_HIRE = 'strong_no_hire';

    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_PHONE => 'Telefon',
            self::TYPE_VIDEO => 'Video',
            self::TYPE_ONSITE => 'Yüz Yüze',
            self::TYPE_TECHNICAL => 'Teknik',
            self::TYPE_HR => 'İK',
            self::TYPE_PANEL => 'Panel',
        ];
    }

    public static function getRecommendationLabels(): array
    {
        return [
            self::RECOMMENDATION_STRONG_HIRE => 'Kesinlikle İşe Alınmalı',
            self::RECOMMENDATION_HIRE => 'İşe Alınmalı',
            self::RECOMMENDATION_NO_DECISION => 'Kararsız',
            self::RECOMMENDATION_NO_HIRE => 'İşe Alınmamalı',
            self::RECOMMENDATION_STRONG_NO_HIRE => 'Kesinlikle İşe Alınmamalı',
        ];
    }

    // Relationships
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(InterviewScorecard::class);
    }
}
