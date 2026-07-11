<?php

namespace App\Models;

use App\Enums\CompanyPackageType;
use App\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'tax_office',
        'tax_number',
        'phone',
        'email',
        'website',
        'address',
        'city',
        'district',
        'postal_code',
        'country',
        'sector',
        'employee_count',
        'logo',
        'settings',
        'package_type',
        'user_limit',
        'storage_limit',
        'license_start_date',
        'license_end_date',
        'status',
        'trial_ends_at',
        // Yeni alanlar
        'license_package_id',
        'location_count',
        'location_limit',
        'employee_limit',
        'current_balance',
    ];

    protected $casts = [
        'settings' => 'array',
        'license_start_date' => 'date',
        'license_end_date' => 'date',
        'trial_ends_at' => 'datetime',
        'current_balance' => 'decimal:2',
        'location_count' => 'integer',
        'location_limit' => 'integer',
        'employee_limit' => 'integer',
        'status' => CompanyStatus::class,
        'package_type' => CompanyPackageType::class,
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Otomatik slug oluştur
        static::creating(function ($company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);

                // Benzersiz yap
                $originalSlug = $company->slug;
                $count = 1;
                while (static::where('slug', $company->slug)->exists()) {
                    $company->slug = $originalSlug.'-'.$count++;
                }
            }
        });
    }

    /**
     * Firma kullanıcıları
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Firma şubeleri
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Aktif şubeler
     */
    public function activeBranches(): HasMany
    {
        return $this->hasMany(Branch::class)->where('is_active', true);
    }

    /**
     * Merkez şube
     */
    public function headquarters(): HasMany
    {
        return $this->hasMany(Branch::class)->where('is_headquarters', true);
    }

    /**
     * Lisans paketi
     */
    public function licensePackage(): BelongsTo
    {
        return $this->belongsTo(LicensePackage::class);
    }

    /**
     * Cari hesap hareketleri
     */
    public function ledger(): HasMany
    {
        return $this->hasMany(CompanyLedger::class)->orderBy('created_at', 'desc');
    }

    /**
     * Firma modülleri
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'company_modules')
            ->withPivot(['is_active', 'activated_at', 'expires_at', 'settings'])
            ->withTimestamps();
    }

    /**
     * Aktif modülleri getir
     */
    public function activeModules(): BelongsToMany
    {
        return $this->modules()->wherePivot('is_active', true);
    }

    /**
     * Firmanın belirli bir modüle erişimi var mı?
     */
    public function hasModule(string $moduleSlug): bool
    {
        return $this->modules()->where('slug', $moduleSlug)->exists();
    }

    /**
     * Firmanın belirli bir modülü aktif mi?
     */
    public function hasActiveModule(string $moduleSlug): bool
    {
        return $this->activeModules()->where('slug', $moduleSlug)->exists();
    }

    /**
     * Firma aktif mi?
     */
    public function isActive(): bool
    {
        return $this->status === CompanyStatus::Active;
    }

    /**
     * Deneme süresi bitti mi?
     */
    public function isTrialExpired(): bool
    {
        if ($this->status !== CompanyStatus::Trial) {
            return false;
        }

        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Lisans geçerli mi?
     */
    public function hasValidLicense(): bool
    {
        if (! $this->license_end_date) {
            return true; // Süresiz lisans
        }

        return $this->license_end_date->isFuture();
    }

    /**
     * Kullanıcı limiti doldu mu?
     */
    public function hasReachedUserLimit(): bool
    {
        return $this->users()->where('is_active', true)->count() >= $this->user_limit;
    }

    /**
     * Kalan kullanıcı sayısı
     */
    public function remainingUserSlots(): int
    {
        $currentUsers = $this->users()->where('is_active', true)->count();

        return max(0, $this->user_limit - $currentUsers);
    }

    /**
     * Ayar değeri al
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Ayar değeri kaydet
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Lisans paketi ata
     */
    public function assignPackage(LicensePackage $package, ?int $durationMonths = null): void
    {
        $duration = $durationMonths ?? $package->duration_months;

        // Package slug'ını enum değerine map et
        $packageTypeMap = [
            'starter' => 'starter',
            'professional' => 'professional',
            'enterprise' => 'enterprise',
            'baslangic' => 'starter',
            'profesyonel' => 'professional',
            'kurumsal' => 'enterprise',
        ];

        $packageType = $packageTypeMap[strtolower($package->slug)] ?? 'starter';

        $this->update([
            'license_package_id' => $package->id,
            'package_type' => $packageType,
            'user_limit' => $package->user_limit ?: 9999,
            'location_limit' => $package->location_limit ?: 9999,
            'employee_limit' => $package->employee_limit ?: 99999,
            'storage_limit' => $package->storage_limit_gb * 1073741824, // GB to bytes
            'license_start_date' => now(),
            'license_end_date' => now()->addMonths($duration),
            'status' => 'active',
        ]);

        // Paketteki modülleri firmaya ata
        $moduleIds = $package->includedModules()->pluck('modules.id')->toArray();
        $syncData = [];
        foreach ($moduleIds as $moduleId) {
            $syncData[$moduleId] = ['is_active' => true, 'activated_at' => now()];
        }
        $this->modules()->sync($syncData);
    }

    /**
     * Lisans süresini uzat
     */
    public function extendLicense(int $months): void
    {
        $currentEnd = $this->license_end_date ?? now();

        // Eğer lisans süresi dolmuşsa bugünden başlat
        if ($currentEnd->isPast()) {
            $currentEnd = now();
        }

        $this->update([
            'license_end_date' => $currentEnd->addMonths($months),
            'status' => 'active',
        ]);
    }

    /**
     * Lokasyon limiti doldu mu?
     */
    public function hasReachedLocationLimit(): bool
    {
        if ($this->location_limit === 0) {
            return false;
        } // Sınırsız
        $currentCount = $this->branches()->where('is_active', true)->count();

        return $currentCount >= $this->location_limit;
    }

    /**
     * Lokasyon sayısını güncelle
     */
    public function updateLocationCount(): void
    {
        $this->update([
            'location_count' => $this->branches()->where('is_active', true)->count(),
        ]);
    }

    /**
     * Personel limiti doldu mu?
     */
    public function hasReachedEmployeeLimit(): bool
    {
        if ($this->employee_limit === 0) {
            return false;
        } // Sınırsız

        return $this->employee_count >= $this->employee_limit;
    }

    /**
     * Cari bakiye (borç pozitif, alacak negatif)
     */
    public function getBalanceLabel(): string
    {
        if ($this->current_balance > 0) {
            return number_format($this->current_balance, 2, ',', '.').' ₺ Borç';
        } elseif ($this->current_balance < 0) {
            return number_format(abs($this->current_balance), 2, ',', '.').' ₺ Alacak';
        }

        return '0,00 ₺';
    }

    /**
     * Borçlu mu?
     */
    public function hasDebt(): bool
    {
        return $this->current_balance > 0;
    }
}
