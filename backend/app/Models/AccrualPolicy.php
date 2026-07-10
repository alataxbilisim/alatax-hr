<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccrualPolicy extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'leave_type_id',
        'name',
        'description',
        'accrual_type',
        'accrual_rate',
        'max_balance',
        'min_balance',
        'tenure_rules',
        'allow_carryover',
        'max_carryover_days',
        'carryover_expiry_date',
        'allow_encashment',
        'max_encashment_days',
        'encashment_rate',
        'waiting_period_days',
        'prorate_first_year',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'accrual_rate' => 'decimal:2',
        'max_balance' => 'decimal:2',
        'min_balance' => 'decimal:2',
        'tenure_rules' => 'array',
        'allow_carryover' => 'boolean',
        'max_carryover_days' => 'decimal:2',
        'carryover_expiry_date' => 'date',
        'allow_encashment' => 'boolean',
        'max_encashment_days' => 'decimal:2',
        'encashment_rate' => 'decimal:2',
        'waiting_period_days' => 'integer',
        'prorate_first_year' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Birikim tipleri
    const TYPE_ANNUAL = 'annual';

    const TYPE_MONTHLY = 'monthly';

    const TYPE_PER_PAY_PERIOD = 'per_pay_period';

    const TYPE_HOURLY = 'hourly';

    const TYPE_CUSTOM = 'custom';

    public static function getAccrualTypes(): array
    {
        return [
            self::TYPE_ANNUAL => 'Yıllık (Tamamı Yılbaşında)',
            self::TYPE_MONTHLY => 'Aylık Birikim',
            self::TYPE_PER_PAY_PERIOD => 'Maaş Dönemi Başına',
            self::TYPE_HOURLY => 'Saat Bazlı',
            self::TYPE_CUSTOM => 'Özel Formül',
        ];
    }

    // Relationships
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AccrualLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Kıdeme göre hak edilen günü hesapla
     */
    public function getDaysForTenure(int $yearsOfService): float
    {
        if (empty($this->tenure_rules)) {
            return $this->accrual_rate;
        }

        // Kıdem kurallarını sırala (yüksekten düşüğe)
        $rules = collect($this->tenure_rules)->sortByDesc('years');

        foreach ($rules as $rule) {
            if ($yearsOfService >= ($rule['years'] ?? 0)) {
                return $rule['days'] ?? $this->accrual_rate;
            }
        }

        return $this->accrual_rate;
    }

    /**
     * Aylık birikim miktarını hesapla
     */
    public function getMonthlyAccrual(int $yearsOfService): float
    {
        $annualDays = $this->getDaysForTenure($yearsOfService);

        switch ($this->accrual_type) {
            case self::TYPE_ANNUAL:
                return 0; // Yılbaşında tamamı verilir
            case self::TYPE_MONTHLY:
                return $annualDays / 12;
            case self::TYPE_PER_PAY_PERIOD:
                return $annualDays / 24; // 2 maaş dönemi/ay varsayımı
            default:
                return 0;
        }
    }

    /**
     * İlk yıl için orantılı hesaplama
     */
    public function calculateProratedDays(int $yearsOfService, \DateTime $startDate): float
    {
        if (! $this->prorate_first_year) {
            return $this->getDaysForTenure($yearsOfService);
        }

        $daysInYear = $startDate->format('L') ? 366 : 365;
        $dayOfYear = $startDate->format('z') + 1;
        $remainingDays = $daysInYear - $dayOfYear;
        $ratio = $remainingDays / $daysInYear;

        return $this->getDaysForTenure($yearsOfService) * $ratio;
    }

    /**
     * Devir hesaplama
     */
    public function calculateCarryover(float $unusedDays): float
    {
        if (! $this->allow_carryover) {
            return 0;
        }

        if ($this->max_carryover_days !== null) {
            return min($unusedDays, $this->max_carryover_days);
        }

        return $unusedDays;
    }
}
