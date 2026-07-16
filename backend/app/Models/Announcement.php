<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use Auditable, BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'title',
        'content',
        'summary',
        'type',
        'category',
        'is_for_all',
        'target_departments',
        'target_positions',
        'target_employees',
        'target_branches',
        'image_path',
        'attachments',
        'is_published',
        'published_at',
        'expires_at',
        'is_pinned',
        'pin_order',
        'view_count',
        'requires_acknowledgment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_for_all' => 'boolean',
        'target_departments' => 'array',
        'target_positions' => 'array',
        'target_employees' => 'array',
        'target_branches' => 'array',
        'attachments' => 'array',
        'is_published' => 'boolean',
        'is_pinned' => 'boolean',
        'requires_acknowledgment' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'view_count' => 'integer',
        'pin_order' => 'integer',
    ];

    /**
     * Oluşturan
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Okunma kayıtları
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * Tip etiketi
     */
    public function getTypeLabelAttribute(): string
    {
        $types = [
            'general' => 'Genel',
            'urgent' => 'Acil',
            'important' => 'Önemli',
            'info' => 'Bilgi',
        ];

        return $types[$this->type] ?? $this->type;
    }

    /**
     * Aktif mi? (Yayınlanmış ve süresi dolmamış)
     */
    public function isActive(): bool
    {
        if (! $this->is_published) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Personel görebilir mi?
     */
    public function canBeViewedBy(Employee $employee): bool
    {
        if (! $this->isActive()) {
            return false;
        }
        if ($this->is_for_all) {
            return true;
        }

        // Departman kontrolü
        if ($this->target_departments && $employee->department_id
            && in_array($employee->department_id, $this->target_departments, true)) {
            return true;
        }

        // Şube kontrolü
        if ($this->target_branches && $employee->branch_id
            && in_array($employee->branch_id, $this->target_branches, true)) {
            return true;
        }

        // Pozisyon kontrolü (legacy string veya position_id)
        if ($this->target_positions) {
            $pos = $employee->position_id ?? $employee->position ?? null;
            if ($pos !== null && in_array($pos, $this->target_positions, false)) {
                return true;
            }
        }

        // Personel kontrolü
        if ($this->target_employees && in_array($employee->id, $this->target_employees, true)) {
            return true;
        }

        return false;
    }

    /**
     * Okundu olarak işaretle (User üzerinden)
     */
    public function markAsReadBy(User $user): void
    {
        $employeeId = Employee::query()->where('user_id', $user->id)->value('id');

        $attrs = [
            'user_id' => $user->id,
            'read_at' => now(),
        ];
        if ($employeeId) {
            $attrs['employee_id'] = $employeeId;
        }

        $existing = $this->reads()->where('user_id', $user->id)->first();
        if ($existing) {
            return;
        }

        $this->reads()->create($attrs);
        $this->increment('view_count');
    }

    /**
     * Okundu olarak işaretle (Employee üzerinden)
     */
    public function markAsReadByEmployee(Employee $employee): void
    {
        $this->markAsReadBy($employee->user);
    }

    /**
     * Kullanıcı tarafından okundu mu?
     */
    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    /**
     * Personel tarafından okundu mu? (Employee üzerinden)
     */
    public function isReadByEmployee(Employee $employee): bool
    {
        return $this->reads()->where('user_id', $employee->user_id)->exists();
    }

    /**
     * Yayınlanmış duyurular
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Aktif duyurular
     */
    public function scopeActive($query)
    {
        return $query->published()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Pinlenmiş önce
     */
    public function scopeOrderByPinned($query)
    {
        return $query->orderByDesc('is_pinned')
            ->orderBy('pin_order')
            ->orderByDesc('published_at');
    }
}
