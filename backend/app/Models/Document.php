<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use Auditable, BelongsToCompany, HasAuditColumns, SoftDeletes;

    /** @var list<string> */
    protected array $auditIgnore = [
        'file_path',
        'metadata',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'category_id',
        'description',
        'version',
        'uploaded_by',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'version' => 'integer',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
