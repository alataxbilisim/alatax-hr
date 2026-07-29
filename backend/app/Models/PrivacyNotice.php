<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrivacyNotice extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'audience',
        'version',
        'title',
        'body',
        'effective_from',
        'is_active',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'effective_from' => 'datetime',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ConsentRecord::class, 'notice_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
