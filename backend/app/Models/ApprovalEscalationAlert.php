<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalEscalationAlert extends Model
{
    use BelongsToCompany, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'approval_record_id',
        'alert_level',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public const LEVEL_REMINDER = 'reminder';

    public const LEVEL_ESCALATED = 'escalated';

    public function record(): BelongsTo
    {
        return $this->belongsTo(ApprovalRecord::class, 'approval_record_id');
    }
}
