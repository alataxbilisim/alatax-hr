<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LicensePackage extends Model
{
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'base_price',
        'annual_price',
        'user_limit',
        'location_limit',
        'employee_limit',
        'storage_limit_gb',
        'duration_months',
        'is_active',
        'is_featured',
        'sort_order',
        'settings',
        'features',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'user_limit' => 'integer',
        'location_limit' => 'integer',
        'employee_limit' => 'integer',
        'storage_limit_gb' => 'integer',
        'duration_months' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
        'features' => 'array',
    ];

    /**
     * Boot method - Otomatik slug oluşturma
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($package) {
            if (empty($package->slug)) {
                $package->slug = Str::slug($package->name);

                // Benzersiz yap
                $originalSlug = $package->slug;
                $count = 1;
                while (static::where('slug', $package->slug)->exists()) {
                    $package->slug = $originalSlug.'-'.$count++;
                }
            }
        });
    }

    /**
     * Bu pakete sahip firmalar
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Paketteki modüller
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'license_package_modules')
            ->withPivot(['is_included', 'additional_price'])
            ->withTimestamps();
    }

    /**
     * Pakete dahil modüller
     */
    public function includedModules(): BelongsToMany
    {
        return $this->modules()->wherePivot('is_included', true);
    }

    /**
     * Limit sınırsız mı kontrolü
     */
    public function hasUnlimitedUsers(): bool
    {
        return $this->user_limit === 0;
    }

    public function hasUnlimitedLocations(): bool
    {
        return $this->location_limit === 0;
    }

    public function hasUnlimitedEmployees(): bool
    {
        return $this->employee_limit === 0;
    }

    public function hasUnlimitedStorage(): bool
    {
        return $this->storage_limit_gb === 0;
    }

    /**
     * Limit açıklaması (UI için)
     */
    public function getUserLimitLabel(): string
    {
        return $this->hasUnlimitedUsers() ? 'Sınırsız' : (string) $this->user_limit;
    }

    public function getLocationLimitLabel(): string
    {
        return $this->hasUnlimitedLocations() ? 'Sınırsız' : (string) $this->location_limit;
    }

    public function getEmployeeLimitLabel(): string
    {
        return $this->hasUnlimitedEmployees() ? 'Sınırsız' : (string) $this->employee_limit;
    }

    public function getStorageLimitLabel(): string
    {
        return $this->hasUnlimitedStorage() ? 'Sınırsız' : $this->storage_limit_gb.' GB';
    }

    /**
     * Yıllık fiyat hesaplama (indirimli)
     */
    public function getAnnualPriceAttribute($value)
    {
        // Yıllık fiyat girilmemişse, aylık x 12 x 0.8 (20% indirim)
        if (! $value && $this->base_price) {
            return $this->base_price * 12 * 0.8;
        }

        return $value;
    }

    /**
     * Aktif paketleri getir
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Öne çıkan paketleri getir
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Sıralı getir
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('base_price');
    }
}
