<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingTemplate extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditColumns;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'tasks',
        'estimated_days',
        'is_active',
        'is_default',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tasks' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    // Relationships
    public function processes()
    {
        return $this->hasMany(OnboardingProcess::class, 'template_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Methods
    public function getTaskCount(): int
    {
        return count($this->tasks ?? []);
    }
}

