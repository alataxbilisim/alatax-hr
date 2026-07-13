<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Holiday extends Model
{
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'date',
        'end_date',
        'type',
        'country_code',
        'is_recurring',
        'is_half_day',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'end_date' => 'date',
        'is_recurring' => 'boolean',
        'is_half_day' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Tatil tipleri
    const TYPE_NATIONAL = 'national';

    const TYPE_RELIGIOUS = 'religious';

    const TYPE_COMPANY = 'company';

    const TYPE_REGIONAL = 'regional';

    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_NATIONAL => 'Ulusal Resmi Tatil',
            self::TYPE_RELIGIOUS => 'Dini Bayram',
            self::TYPE_COMPANY => 'Şirket Tatili',
            self::TYPE_REGIONAL => 'Bölgesel Tatil',
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->whereNull('company_id'); // Sistem geneli tatiller
            if ($companyId) {
                $q->orWhere('company_id', $companyId);
            }
        });
    }

    public function scopeForCountry($query, string $countryCode = 'TR')
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->whereNotNull('end_date')
                        ->where('date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                });
        });
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('date', $year);
    }

    /**
     * Belirli bir tarihin tatil olup olmadığını kontrol et
     */
    public static function isHoliday(Carbon $date, ?int $companyId = null, string $countryCode = 'TR'): bool
    {
        return self::active()
            ->forCompany($companyId)
            ->forCountry($countryCode)
            ->where(function ($q) use ($date) {
                $q->whereDate('date', $date)
                    ->orWhere(function ($q2) use ($date) {
                        $q2->whereNotNull('end_date')
                            ->where('date', '<=', $date)
                            ->where('end_date', '>=', $date);
                    });
            })
            ->exists();
    }

    /**
     * İki tarih arasındaki tatil günlerini hesapla
     */
    public static function countHolidaysBetween(Carbon $startDate, Carbon $endDate, ?int $companyId = null, string $countryCode = 'TR'): int
    {
        $holidays = self::active()
            ->forCompany($companyId)
            ->forCountry($countryCode)
            ->inDateRange($startDate, $endDate)
            ->get();

        $count = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if (self::isHoliday($current, $companyId, $countryCode)) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    /**
     * Toplam tatil gün sayısı
     */
    public function getTotalDays(): float
    {
        if ($this->end_date) {
            $days = $this->date->diffInDays($this->end_date) + 1;
        } else {
            $days = 1;
        }

        return $this->is_half_day ? $days * 0.5 : $days;
    }

    /**
     * Türkiye resmi tatillerini seed et (ulusal + dini 2026–2028 tablosu).
     */
    public static function seedTurkishHolidays(int $year): void
    {
        $holidays = self::nationalHolidaysForYear($year);

        foreach (self::religiousHolidaysForYear($year) as $religious) {
            $holidays[] = $religious;
        }

        foreach ($holidays as $holiday) {
            $existing = self::query()
                ->whereNull('company_id')
                ->whereDate('date', $holiday['date'])
                ->where('country_code', 'TR')
                ->where('name', $holiday['name'])
                ->first();

            $payload = array_merge($holiday, [
                'company_id' => null,
                'country_code' => 'TR',
                'is_active' => true,
            ]);

            if ($existing) {
                $existing->update($payload);
            } else {
                self::create($payload);
            }
        }
    }

    /**
     * @param  list<int>  $years
     */
    public static function seedTurkishHolidaysForYears(array $years): void
    {
        foreach ($years as $year) {
            self::seedTurkishHolidays((int) $year);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function nationalHolidaysForYear(int $year): array
    {
        return [
            [
                'name' => 'Yılbaşı',
                'date' => "{$year}-01-01",
                'type' => self::TYPE_NATIONAL,
                'is_recurring' => true,
                'is_half_day' => false,
            ],
            [
                'name' => 'Ulusal Egemenlik ve Çocuk Bayramı',
                'date' => "{$year}-04-23",
                'type' => self::TYPE_NATIONAL,
                'is_recurring' => true,
                'is_half_day' => false,
            ],
            [
                'name' => 'Emek ve Dayanışma Günü',
                'date' => "{$year}-05-01",
                'type' => self::TYPE_NATIONAL,
                'is_recurring' => true,
                'is_half_day' => false,
            ],
            [
                'name' => 'Atatürk\'ü Anma, Gençlik ve Spor Bayramı',
                'date' => "{$year}-05-19",
                'type' => self::TYPE_NATIONAL,
                'is_recurring' => true,
                'is_half_day' => false,
            ],
            [
                'name' => 'Demokrasi ve Milli Birlik Günü',
                'date' => "{$year}-07-15",
                'type' => self::TYPE_NATIONAL,
                'is_recurring' => true,
                'is_half_day' => false,
            ],
            [
                'name' => 'Zafer Bayramı',
                'date' => "{$year}-08-30",
                'type' => self::TYPE_NATIONAL,
                'is_recurring' => true,
                'is_half_day' => false,
            ],
            [
                'name' => 'Cumhuriyet Bayramı Arifesi',
                'date' => "{$year}-10-28",
                'type' => self::TYPE_NATIONAL,
                'is_recurring' => true,
                'is_half_day' => true,
            ],
            [
                'name' => 'Cumhuriyet Bayramı',
                'date' => "{$year}-10-29",
                'type' => self::TYPE_NATIONAL,
                'is_recurring' => true,
                'is_half_day' => false,
            ],
        ];
    }

    /**
     * Dini bayramlar — sabit tarih tablosu (2026–2028). Diğer yıllar boş.
     *
     * @return list<array<string, mixed>>
     */
    public static function religiousHolidaysForYear(int $year): array
    {
        $table = [
            2026 => [
                ['name' => 'Ramazan Bayramı', 'date' => '2026-03-20', 'end_date' => '2026-03-22'],
                ['name' => 'Kurban Bayramı', 'date' => '2026-05-27', 'end_date' => '2026-05-30'],
            ],
            2027 => [
                ['name' => 'Ramazan Bayramı', 'date' => '2027-03-09', 'end_date' => '2027-03-11'],
                ['name' => 'Kurban Bayramı', 'date' => '2027-05-16', 'end_date' => '2027-05-19'],
            ],
            2028 => [
                ['name' => 'Ramazan Bayramı', 'date' => '2028-02-26', 'end_date' => '2028-02-28'],
                ['name' => 'Kurban Bayramı', 'date' => '2028-05-04', 'end_date' => '2028-05-07'],
            ],
        ];

        $rows = [];
        foreach ($table[$year] ?? [] as $item) {
            $rows[] = [
                'name' => $item['name'],
                'date' => $item['date'],
                'end_date' => $item['end_date'],
                'type' => self::TYPE_RELIGIOUS,
                'is_recurring' => false,
                'is_half_day' => false,
            ];
        }

        return $rows;
    }
}
