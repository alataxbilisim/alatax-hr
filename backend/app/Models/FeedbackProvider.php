<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_review_id',
        'provider_id',
        'relationship',
        'status',
        'invited_at',
        'submitted_at',
        'deadline',
        'decline_reason',
        'is_anonymous',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'submitted_at' => 'datetime',
        'deadline' => 'datetime',
        'is_anonymous' => 'boolean',
    ];

    const RELATIONSHIP_SELF = 'self';
    const RELATIONSHIP_MANAGER = 'manager';
    const RELATIONSHIP_PEER = 'peer';
    const RELATIONSHIP_DIRECT_REPORT = 'direct_report';
    const RELATIONSHIP_EXTERNAL = 'external';

    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_DECLINED = 'declined';

    public static function getRelationshipLabels(): array
    {
        return [
            self::RELATIONSHIP_SELF => 'Öz Değerlendirme',
            self::RELATIONSHIP_MANAGER => 'Yönetici',
            self::RELATIONSHIP_PEER => 'İş Arkadaşı',
            self::RELATIONSHIP_DIRECT_REPORT => 'Ast',
            self::RELATIONSHIP_EXTERNAL => 'Dış Paydaş',
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Bekliyor',
            self::STATUS_IN_PROGRESS => 'Devam Ediyor',
            self::STATUS_SUBMITTED => 'Gönderildi',
            self::STATUS_DECLINED => 'Reddedildi',
        ];
    }

    // Relationships
    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FeedbackResponse::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    // Methods
    public function submit(): void
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function decline(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_DECLINED,
            'decline_reason' => $reason,
        ]);
    }

    public function getAverageScore(): ?float
    {
        $responses = $this->responses()->whereNotNull('score')->get();
        
        if ($responses->isEmpty()) {
            return null;
        }

        return $responses->avg('score');
    }
}


