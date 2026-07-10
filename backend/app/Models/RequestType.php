<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestType extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'requires_approval',
        'requires_attachment',
        'approval_flow',
        'form_fields',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'requires_attachment' => 'boolean',
        'is_active' => 'boolean',
        'approval_flow' => 'array',
        'form_fields' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Bu tipteki talepler
     */
    public function requests(): HasMany
    {
        return $this->hasMany(EmployeeRequest::class);
    }

    /**
     * Aktif tipler
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Sıralı
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
