<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * D1e — Rapor erişim denetimi (append-only, KVKK hesap verebilirlik).
 */
class ReportAccessLog extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'report_id',
        'dashboard_id',
        'action',
        'dataset_key',
        'row_count',
        'contains_sensitive',
        'sensitive_fields',
        'filters_hash',
        'filter_field_keys',
        'duration_ms',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'contains_sensitive' => 'boolean',
        'sensitive_fields' => 'array',
        'filter_field_keys' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('report_access_logs append-only: güncelleme yasak');
        });
        static::deleting(function () {
            throw new LogicException('report_access_logs append-only: silme yasak');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class, 'report_id');
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }
}
