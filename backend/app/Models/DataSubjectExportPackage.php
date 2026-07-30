<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DataSubjectExportPackage extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'data_subject_request_id',
        'uuid',
        'status',
        'storage_path',
        'json_path',
        'human_path',
        'error_message',
        'expires_at',
        'purged_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'purged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(DataSubjectRequest::class, 'data_subject_request_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(DataSubjectExportAccessLog::class, 'export_package_id');
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'ready'
            && $this->storage_path
            && $this->expires_at
            && $this->expires_at->isFuture()
            && $this->purged_at === null;
    }
}
