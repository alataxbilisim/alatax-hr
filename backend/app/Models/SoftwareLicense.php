<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftwareLicense extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'vendor',
        'version',
        'license_type',
        'total_seats',
        'used_seats',
        'purchase_date',
        'expiry_date',
        'purchase_cost',
        'annual_cost',
        'currency',
        'license_key',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_seats' => 'integer',
        'used_seats' => 'integer',
        'purchase_date' => 'date',
        'expiry_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'annual_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    const TYPE_PERPETUAL = 'perpetual';

    const TYPE_SUBSCRIPTION = 'subscription';

    const TYPE_PER_SEAT = 'per_seat';

    const TYPE_CONCURRENT = 'concurrent';

    const TYPE_SITE = 'site';

    const TYPE_OPEN_SOURCE = 'open_source';

    public static function getLicenseTypes(): array
    {
        return [
            self::TYPE_PERPETUAL => 'Kalıcı',
            self::TYPE_SUBSCRIPTION => 'Abonelik',
            self::TYPE_PER_SEAT => 'Kullanıcı Başı',
            self::TYPE_CONCURRENT => 'Eşzamanlı',
            self::TYPE_SITE => 'Site Lisansı',
            self::TYPE_OPEN_SOURCE => 'Açık Kaynak',
        ];
    }

    // Relationships
    public function assignments(): HasMany
    {
        return $this->hasMany(SoftwareLicenseAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(SoftwareLicenseAssignment::class)->where('is_active', true);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now());
    }

    // Methods
    public function hasAvailableSeats(): bool
    {
        if ($this->total_seats === null) {
            return true;
        }

        return $this->used_seats < $this->total_seats;
    }

    public function getAvailableSeats(): ?int
    {
        if ($this->total_seats === null) {
            return null;
        }

        return $this->total_seats - $this->used_seats;
    }

    public function isExpired(): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date < now();
    }

    public function updateUsedSeats(): void
    {
        $this->used_seats = $this->activeAssignments()->count();
        $this->save();
    }
}
