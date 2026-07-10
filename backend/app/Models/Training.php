<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Training extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'category',
        'type',
        'instructor',
        'location',
        'duration_hours',
        'max_participants',
        'cost',
        'is_mandatory',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
        'duration_hours' => 'integer',
        'max_participants' => 'integer',
        'cost' => 'decimal:2',
    ];

    /**
     * Eğitim oturumları
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    /**
     * Oluşturan kullanıcı
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Aktif eğitimler
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Zorunlu eğitimler
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Toplam katılımcı sayısı
     */
    public function getTotalParticipantsAttribute(): int
    {
        return $this->sessions()
            ->withCount('participants')
            ->get()
            ->sum('participants_count');
    }
}
