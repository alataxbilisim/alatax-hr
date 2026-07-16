<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Firma bazlı bildirim şablon override (4C-2).
 */
class NotificationTemplate extends Model
{
    use Auditable, BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'event_key',
        'subject',
        'body',
        'created_by',
        'updated_by',
    ];
}
