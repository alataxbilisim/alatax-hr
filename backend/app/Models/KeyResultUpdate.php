<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeyResultUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key_result_id',
        'user_id',
        'previous_value',
        'new_value',
        'note',
        'confidence',
    ];

    protected $casts = [
        'previous_value' => 'decimal:2',
        'new_value' => 'decimal:2',
    ];

    const CONFIDENCE_LOW = 'low';

    const CONFIDENCE_MEDIUM = 'medium';

    const CONFIDENCE_HIGH = 'high';

    public static function getConfidenceLevels(): array
    {
        return [
            self::CONFIDENCE_LOW => 'Düşük',
            self::CONFIDENCE_MEDIUM => 'Orta',
            self::CONFIDENCE_HIGH => 'Yüksek',
        ];
    }

    // Relationships
    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(KeyResult::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper
    public function getChangeAmount(): float
    {
        return $this->new_value - $this->previous_value;
    }

    public function getChangePercentage(): float
    {
        if ($this->previous_value == 0) {
            return $this->new_value > 0 ? 100 : 0;
        }

        return (($this->new_value - $this->previous_value) / $this->previous_value) * 100;
    }
}
