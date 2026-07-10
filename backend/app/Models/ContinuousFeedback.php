<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContinuousFeedback extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'continuous_feedbacks';

    protected $fillable = [
        'company_id',
        'from_user_id',
        'to_user_id',
        'type',
        'content',
        'tags',
        'is_public',
        'is_anonymous',
        'related_type',
        'related_id',
        'created_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_public' => 'boolean',
        'is_anonymous' => 'boolean',
    ];

    const TYPE_PRAISE = 'praise';
    const TYPE_SUGGESTION = 'suggestion';
    const TYPE_CONCERN = 'concern';
    const TYPE_COACHING = 'coaching';

    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_PRAISE => 'Takdir/Övgü',
            self::TYPE_SUGGESTION => 'Öneri',
            self::TYPE_CONCERN => 'Endişe',
            self::TYPE_COACHING => 'Koçluk',
        ];
    }

    public static function getTypeColors(): array
    {
        return [
            self::TYPE_PRAISE => 'green',
            self::TYPE_SUGGESTION => 'blue',
            self::TYPE_CONCERN => 'yellow',
            self::TYPE_COACHING => 'purple',
        ];
    }

    // Relationships
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('to_user_id', $userId);
    }

    public function scopeFromUser($query, int $userId)
    {
        return $query->where('from_user_id', $userId);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Accessors
    public function getSenderNameAttribute(): ?string
    {
        if ($this->is_anonymous) {
            return null;
        }
        return $this->fromUser?->name;
    }
}


