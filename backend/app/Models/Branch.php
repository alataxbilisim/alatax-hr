<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'address',
        'city',
        'district',
        'postal_code',
        'country',
        'phone',
        'email',
        'manager_id',
        'is_active',
        'is_headquarters',
        'latitude',
        'longitude',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_headquarters' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Şube yöneticisi
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Şubedeki çalışanlar
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }

    /**
     * Aktif şubeler
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Merkez şube
     */
    public function scopeHeadquarters($query)
    {
        return $query->where('is_headquarters', true);
    }

    /**
     * Şirket bazlı sıralı
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('is_headquarters', 'desc')
            ->orderBy('name');
    }

    /**
     * Merkez şube mi?
     */
    public function isHeadquarters(): bool
    {
        return $this->is_headquarters;
    }

    /**
     * Tam adres
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->district,
            $this->city,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Merkez şube yap
     */
    public function setAsHeadquarters(): void
    {
        // Aynı şirketteki diğer şubeleri merkez şube yapmaktan çıkar
        static::where('company_id', $this->company_id)
            ->where('id', '!=', $this->id)
            ->update(['is_headquarters' => false]);

        $this->update(['is_headquarters' => true]);
    }
}
