<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only imha tutanağı — kanıt yükümlülüğü. */
class DestructionLog extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'destruction_candidate_id',
        'approval_id',
        'retention_policy_id',
        'subject_type',
        'subject_id',
        'data_category',
        'strategy',
        'collector_key',
        'rows_affected',
        'summary',
        'content_hash',
        'approved_by',
        'dry_run',
        'outcome',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'rows_affected' => 'integer',
        'summary' => 'array',
        'dry_run' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('destruction_logs append-only');
        });
        static::deleting(function (): void {
            throw new LogicException('destruction_logs append-only');
        });
    }
}
