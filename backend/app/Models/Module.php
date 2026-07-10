<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_core',
        'price_monthly',
        'price_yearly',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_core' => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Modülü kullanan firmalar
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_modules')
            ->withPivot(['is_active', 'activated_at', 'expires_at', 'settings'])
            ->withTimestamps();
    }

    /**
     * Core modüller (her zaman aktif)
     */
    public function scopeCore($query)
    {
        return $query->where('is_core', true);
    }

    /**
     * Satılabilir modüller
     */
    public function scopePurchasable($query)
    {
        return $query->where('is_core', false)->where('is_active', true);
    }

    /**
     * Aktif modüller
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Yıllık indirim oranı
     */
    public function getYearlyDiscountPercentage(): float
    {
        if ($this->price_monthly <= 0) {
            return 0;
        }

        $yearlyWithoutDiscount = $this->price_monthly * 12;
        $discount = (($yearlyWithoutDiscount - $this->price_yearly) / $yearlyWithoutDiscount) * 100;

        return round($discount, 1);
    }
}
