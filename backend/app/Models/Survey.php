<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'type',
        'is_anonymous',
        'is_active',
        'start_date',
        'end_date',
        'recurrence',
        'audience',
        'audience_filter',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'audience_filter' => 'array',
    ];

    const TYPE_ENGAGEMENT = 'engagement';

    const TYPE_SATISFACTION = 'satisfaction';

    const TYPE_PULSE = 'pulse';

    const TYPE_ENPS = 'enps';

    const TYPE_ONBOARDING = 'onboarding';

    const TYPE_EXIT = 'exit';

    const TYPE_CUSTOM = 'custom';

    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_ENGAGEMENT => 'Çalışan Bağlılığı',
            self::TYPE_SATISFACTION => 'Memnuniyet',
            self::TYPE_PULSE => 'Nabız Yoklaması',
            self::TYPE_ENPS => 'Employee NPS',
            self::TYPE_ONBOARDING => 'Onboarding Deneyimi',
            self::TYPE_EXIT => 'Çıkış Mülakatı',
            self::TYPE_CUSTOM => 'Özel',
        ];
    }

    // Relationships
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('order_number');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(SurveySubmission::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOpen($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', now());
        });
    }

    // Methods
    public function isOpen(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->start_date && $this->start_date > now()) {
            return false;
        }
        if ($this->end_date && $this->end_date < now()) {
            return false;
        }

        return true;
    }

    public function getCompletionRate(): float
    {
        $completed = $this->submissions()->where('status', 'completed')->count();
        $total = $this->submissions()->count();

        return $total > 0 ? ($completed / $total) * 100 : 0;
    }
}
