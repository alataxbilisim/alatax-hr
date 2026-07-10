<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KeyResult extends Model
{
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'objective_id',
        'owner_id',
        'title',
        'description',
        'metric_type',
        'start_value',
        'target_value',
        'current_value',
        'progress',
        'weight',
        'status',
        'due_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'progress' => 'decimal:2',
        'weight' => 'decimal:2',
        'due_date' => 'date',
    ];

    const METRIC_NUMBER = 'number';

    const METRIC_PERCENTAGE = 'percentage';

    const METRIC_CURRENCY = 'currency';

    const METRIC_BOOLEAN = 'boolean';

    const METRIC_MILESTONE = 'milestone';

    const STATUS_NOT_STARTED = 'not_started';

    const STATUS_ON_TRACK = 'on_track';

    const STATUS_AT_RISK = 'at_risk';

    const STATUS_BEHIND = 'behind';

    const STATUS_COMPLETED = 'completed';

    public static function getMetricTypes(): array
    {
        return [
            self::METRIC_NUMBER => 'Sayısal',
            self::METRIC_PERCENTAGE => 'Yüzde',
            self::METRIC_CURRENCY => 'Para',
            self::METRIC_BOOLEAN => 'Evet/Hayır',
            self::METRIC_MILESTONE => 'Kilometre Taşı',
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_NOT_STARTED => 'Başlamadı',
            self::STATUS_ON_TRACK => 'Yolunda',
            self::STATUS_AT_RISK => 'Risk Altında',
            self::STATUS_BEHIND => 'Geride',
            self::STATUS_COMPLETED => 'Tamamlandı',
        ];
    }

    // Relationships
    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(KeyResultUpdate::class)->orderBy('created_at', 'desc');
    }

    // Methods
    public function updateValue(float $newValue, ?string $note = null, string $confidence = 'medium'): void
    {
        $previousValue = $this->current_value;
        $this->current_value = $newValue;
        $this->calculateProgress();
        $this->save();

        // Güncelleme kaydı oluştur
        KeyResultUpdate::create([
            'key_result_id' => $this->id,
            'user_id' => auth()->id(),
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'note' => $note,
            'confidence' => $confidence,
        ]);

        // Hedef ilerlemesini güncelle
        $this->objective->updateProgress();
    }

    protected function calculateProgress(): void
    {
        if ($this->metric_type === self::METRIC_BOOLEAN) {
            $this->progress = $this->current_value > 0 ? 100 : 0;
        } else {
            $range = $this->target_value - $this->start_value;
            if ($range == 0) {
                $this->progress = $this->current_value >= $this->target_value ? 100 : 0;
            } else {
                $progress = (($this->current_value - $this->start_value) / $range) * 100;
                $this->progress = max(0, min(100, $progress));
            }
        }

        // Durumu güncelle
        $this->status = $this->calculateStatus();
    }

    protected function calculateStatus(): string
    {
        if ($this->progress >= 100) {
            return self::STATUS_COMPLETED;
        }

        if ($this->progress == 0) {
            return self::STATUS_NOT_STARTED;
        }

        // Due date'e göre beklenen ilerleme
        if ($this->due_date) {
            $objective = $this->objective;
            $totalDays = $objective->start_date->diffInDays($this->due_date);
            $elapsedDays = $objective->start_date->diffInDays(now());

            if ($totalDays > 0) {
                $expectedProgress = ($elapsedDays / $totalDays) * 100;

                if ($this->progress >= $expectedProgress) {
                    return self::STATUS_ON_TRACK;
                } elseif ($this->progress >= $expectedProgress * 0.7) {
                    return self::STATUS_AT_RISK;
                } else {
                    return self::STATUS_BEHIND;
                }
            }
        }

        return self::STATUS_ON_TRACK;
    }
}
