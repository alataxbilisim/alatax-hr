<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformancePeriod extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Dönemdeki değerlendirmeler
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'period_id');
    }

    /**
     * Oluşturan kullanıcı
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Aktif dönemler
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Dönem sürmekte mi?
     */
    public function isOngoing(): bool
    {
        return $this->status === 'active' &&
               now()->between($this->start_date, $this->end_date);
    }
}
