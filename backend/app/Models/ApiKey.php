<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'key',
        'description',
        'permissions',
        'last_used_at',
        'expires_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'key',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($apiKey) {
            if (empty($apiKey->key)) {
                $apiKey->key = 'ak_'.Str::random(60);
            }
        });
    }

    /**
     * Oluşturan kullanıcı
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Aktif mi?
     */
    public function isActive(): bool
    {
        return $this->is_active && (! $this->expires_at || $this->expires_at->isFuture());
    }

    /**
     * Süresi dolmuş mu?
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Son kullanımı güncelle
     */
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
