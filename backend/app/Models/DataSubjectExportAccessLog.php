<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * D2b — İhraç paketi indirme logu (append-only, ReportAccessLog deseni).
 */
class DataSubjectExportAccessLog extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'export_package_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('data_subject_export_access_logs append-only');
        });
        static::deleting(function (): void {
            throw new LogicException('data_subject_export_access_logs append-only');
        });
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(DataSubjectExportPackage::class, 'export_package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
