<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payslip extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'period',
        'year',
        'month',
        'gross_salary',
        'net_salary',
        'deductions',
        'bonuses',
        'total_deductions',
        'total_bonuses',
        'worked_days',
        'overtime_hours',
        'file_path',
        'is_published',
        'published_at',
        'published_by',
        'is_viewed',
        'viewed_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_bonuses' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'deductions' => 'array',
        'bonuses' => 'array',
        'is_published' => 'boolean',
        'is_viewed' => 'boolean',
        'published_at' => 'datetime',
        'viewed_at' => 'datetime',
        'year' => 'integer',
        'month' => 'integer',
        'worked_days' => 'integer',
    ];

    protected $hidden = [
        'gross_salary',
        'net_salary',
        'deductions',
        'bonuses',
    ];

    /**
     * Personel
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Yayınlayan
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Dönem formatı (Ocak 2024)
     */
    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
        ];

        return ($months[$this->month] ?? '').' '.$this->year;
    }

    /**
     * Yayınlanmış
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Yıla göre filtrele
     */
    public function scopeOfYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Görüntülendi olarak işaretle
     */
    public function markAsViewed(): void
    {
        if (! $this->is_viewed) {
            $this->update([
                'is_viewed' => true,
                'viewed_at' => now(),
            ]);
        }
    }

    /**
     * Yayınla
     */
    public function publish(): void
    {
        $this->update([
            'is_published' => true,
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);
    }
}
