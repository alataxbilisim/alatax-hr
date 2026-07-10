<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Webhook extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditColumns;

    protected $fillable = [
        'company_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'timeout',
        'retry_count',
        'last_triggered_at',
        'success_count',
        'failure_count',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($webhook) {
            if (empty($webhook->secret)) {
                $webhook->secret = Str::random(32);
            }
        });
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class)->orderBy('triggered_at', 'desc');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}

