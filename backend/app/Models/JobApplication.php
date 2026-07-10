<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobApplication extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'job_position_id',
        'position_id',
        'form_id',
        'first_name',
        'last_name',
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'email',
        'phone',
        'cv_path',
        'cv_original_name',
        'form_data',
        'status',
        'rating',
        'notes',
        'internal_notes',
        'tags',
        'source',
        'referrer',
        'assigned_to',
        'ip_address',
        'user_agent',
        'updated_by',
    ];

    protected $casts = [
        'form_data' => 'array',
        'tags' => 'array',
        'rating' => 'integer',
    ];

    protected $appends = ['full_name'];

    // Constants for status
    const STATUS_NEW = 'new';
    const STATUS_REVIEWING = 'reviewing';
    const STATUS_SHORTLISTED = 'shortlisted';
    const STATUS_INTERVIEW_SCHEDULED = 'interview_scheduled';
    const STATUS_INTERVIEWED = 'interviewed';
    const STATUS_OFFER_SENT = 'offer_sent';
    const STATUS_HIRED = 'hired';
    const STATUS_REJECTED = 'rejected';
    const STATUS_WITHDRAWN = 'withdrawn';

    // Status labels (Turkish)
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_NEW => 'Yeni',
            self::STATUS_REVIEWING => 'İnceleniyor',
            self::STATUS_SHORTLISTED => 'Ön Seçim',
            self::STATUS_INTERVIEW_SCHEDULED => 'Mülakat Planlandı',
            self::STATUS_INTERVIEWED => 'Mülakat Yapıldı',
            self::STATUS_OFFER_SENT => 'Teklif Gönderildi',
            self::STATUS_HIRED => 'İşe Alındı',
            self::STATUS_REJECTED => 'Reddedildi',
            self::STATUS_WITHDRAWN => 'Çekildi',
        ];
    }

    // Relationships
    public function jobPosition()
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function position()
    {
        return $this->belongsTo(JobPosition::class, 'position_id');
    }

    public function form()
    {
        return $this->belongsTo(ApplicationForm::class, 'form_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusLogs()
    {
        return $this->hasMany(ApplicationStatusLog::class);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getStatusLabelAttribute()
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_HIRED,
            self::STATUS_REJECTED,
            self::STATUS_WITHDRAWN,
        ]);
    }

    // Methods
    public function changeStatus(string $newStatus, ?string $note = null, ?int $changedBy = null): void
    {
        $oldStatus = $this->status;
        
        $this->update([
            'status' => $newStatus,
            'updated_by' => $changedBy ?? auth()->id(),
        ]);

        // Log the status change
        $this->statusLogs()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'note' => $note,
            'changed_by' => $changedBy ?? auth()->id(),
        ]);
    }
}

