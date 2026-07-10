<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionCompetency extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'position_name',
        'competency_id',
        'expected_level',
        'weight',
        'is_required',
    ];

    protected $casts = [
        'expected_level' => 'integer',
        'weight' => 'decimal:2',
        'is_required' => 'boolean',
    ];

    // Relationships
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    // Scopes
    public function scopeForPosition($query, string $positionName)
    {
        return $query->where('position_name', $positionName);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }
}


