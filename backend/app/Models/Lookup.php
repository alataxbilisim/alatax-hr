<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lookup Engine satırı.
 * BelongsToCompany kullanılmaz: company_id nullable (sistem satırları + firma override birleşimi).
 */
class Lookup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'lookup_type',
        'value',
        'label',
        'color',
        'sort_order',
        'is_active',
        'is_system',
        'parent_lookup_id',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_lookup_id');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('lookup_type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isHybrid(): bool
    {
        return (bool) data_get($this->meta, 'hybrid', false);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'lookup_type' => $this->lookup_type,
            'value' => $this->value,
            'label' => $this->label,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'is_hybrid' => $this->isHybrid(),
            'parent_lookup_id' => $this->parent_lookup_id,
            'meta' => $this->meta,
            'is_company_override' => $this->company_id !== null,
        ];
    }
}
