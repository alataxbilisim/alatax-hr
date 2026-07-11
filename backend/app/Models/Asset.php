<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'category_id',
        'name',
        'asset_code',
        'serial_number',
        'brand',
        'model',
        'description',
        'purchase_date',
        'purchase_price',
        'warranty_end_date',
        'condition',
        'status',
        'location',
        'specifications',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_end_date' => 'date',
        'purchase_price' => 'decimal:2',
        'specifications' => 'array',
        'condition' => AssetCondition::class,
        'status' => AssetStatus::class,
    ];

    /**
     * Kategori
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    /**
     * Zimmet kayıtları
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Aktif zimmet
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)
            ->whereNull('return_date')
            ->latest();
    }

    /**
     * Bakım kayıtları
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }

    /**
     * Oluşturan kullanıcı
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Kullanılabilir varlıklar
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Zimmetli varlıklar
     */
    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    /**
     * Garanti süresi var mı?
     */
    public function hasValidWarranty(): bool
    {
        return $this->warranty_end_date && $this->warranty_end_date->isFuture();
    }

    /**
     * Şu anda kime zimmetli?
     */
    public function getCurrentAssigneeAttribute(): ?User
    {
        return $this->currentAssignment?->user;
    }
}
