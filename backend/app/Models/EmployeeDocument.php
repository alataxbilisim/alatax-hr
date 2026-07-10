<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, HasAuditColumns;

    protected $fillable = [
        'company_id',
        'employee_id',
        'title',
        'description',
        'category',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'issue_date',
        'expiry_date',
        'is_expired',
        'is_visible_to_employee',
        'status',
        'notes',
        'uploaded_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_expired' => 'boolean',
        'is_visible_to_employee' => 'boolean',
        'file_size' => 'integer',
    ];

    const CATEGORY_ID_CARD = 'id_card';
    const CATEGORY_CONTRACT = 'contract';
    const CATEGORY_CERTIFICATE = 'certificate';
    const CATEGORY_EDUCATION = 'education';
    const CATEGORY_HEALTH = 'health';
    const CATEGORY_OTHER = 'other';

    /**
     * Personel
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Yükleyen
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Kategori etiketi
     */
    public function getCategoryLabelAttribute(): string
    {
        $categories = [
            self::CATEGORY_ID_CARD => 'Kimlik',
            self::CATEGORY_CONTRACT => 'Sözleşme',
            self::CATEGORY_CERTIFICATE => 'Sertifika',
            self::CATEGORY_EDUCATION => 'Eğitim',
            self::CATEGORY_HEALTH => 'Sağlık',
            self::CATEGORY_OTHER => 'Diğer',
        ];
        return $categories[$this->category] ?? $this->category;
    }

    /**
     * Dosya boyutu formatı
     */
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * Süresi dolmuş mu?
     */
    public function checkExpiry(): bool
    {
        if (!$this->expiry_date) return false;
        
        $isExpired = $this->expiry_date->isPast();
        
        if ($this->is_expired !== $isExpired) {
            $this->update(['is_expired' => $isExpired]);
        }
        
        return $isExpired;
    }

    /**
     * Personele görünür belgeler
     */
    public function scopeVisibleToEmployee($query)
    {
        return $query->where('is_visible_to_employee', true);
    }

    /**
     * Aktif belgeler
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Kategoriye göre
     */
    public function scopeOfCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}

