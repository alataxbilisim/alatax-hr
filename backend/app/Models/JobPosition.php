<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\JobPositionStatus;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class JobPosition extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'title',
        'slug',
        'description',
        'requirements',
        'responsibilities',
        'department',
        'location',
        'employment_type',
        'experience_level',
        'salary_min',
        'salary_max',
        'salary_visible',
        'form_id',
        'status',
        'positions_count',
        'application_deadline',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
        'salary_visible' => 'boolean',
        'positions_count' => 'integer',
        'application_deadline' => 'date',
        'published_at' => 'datetime',
        'employment_type' => EmploymentType::class,
        'experience_level' => ExperienceLevel::class,
        'status' => JobPositionStatus::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title).'-'.uniqid();
            }
        });
    }

    // Relationships
    public function form()
    {
        return $this->belongsTo(ApplicationForm::class);
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    // Accessors
    public function getApplicationsCountAttribute()
    {
        return $this->applications()->count();
    }

    public function getNewApplicationsCountAttribute()
    {
        return $this->applications()->where('status', 'new')->count();
    }

    // Helpers
    public function publish()
    {
        $this->update([
            'status' => 'active',
            'published_at' => now(),
        ]);
    }

    public function pause()
    {
        $this->update(['status' => 'paused']);
    }

    public function close()
    {
        $this->update(['status' => 'closed']);
    }
}
