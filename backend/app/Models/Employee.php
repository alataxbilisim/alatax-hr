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

class Employee extends Model
{
    use Auditable, BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    /**
     * Audit v2 — hassas alanlar diff'te maskelenir.
     *
     * @var list<string>
     */
    protected array $auditMasked = [
        'national_id',
        'gross_salary',
        'net_salary',
        'bank_name',
        'iban',
        'sgk_number',
        'sgk_start_date',
    ];

    /**
     * @var array<string, string>
     */
    protected array $auditMaskedLabels = [
        'national_id' => 'tckn',
        'gross_salary' => 'maaş',
        'net_salary' => 'net maaş',
        'bank_name' => 'banka',
        'iban' => 'iban',
        'sgk_number' => 'sgk',
        'sgk_start_date' => 'sgk başlangıç',
    ];

    protected $fillable = [
        'company_id',
        'user_id',
        'department_id',
        'employee_code',
        'title',
        'position',
        'manager_id',
        'birth_date',
        'national_id',
        'gender',
        'marital_status',
        'blood_type',
        'education_level',
        'personal_email',
        'personal_phone',
        'address',
        'city',
        'district',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'hire_date',
        'contract_start_date',
        'contract_end_date',
        'contract_type',
        'work_type',
        'gross_salary',
        'net_salary',
        'currency',
        'bank_name',
        'iban',
        'sgk_number',
        'sgk_start_date',
        'status',
        'termination_date',
        'termination_reason',
        'notes',
        'custom_fields',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'sgk_start_date' => 'date',
        'termination_date' => 'date',
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'custom_fields' => 'array',
    ];

    // Hassas alanlar: global $hidden KALDIRILDI — API çıkışı EmployeeResource + izin ile (Faz 2)

    /**
     * İlişkili kullanıcı hesabı
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Departman
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Yönetici (üst)
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Astlar
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /**
     * Bordroları
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Belgeleri
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * Talepleri
     */
    public function requests(): HasMany
    {
        return $this->hasMany(EmployeeRequest::class);
    }

    /**
     * İzin talepleri
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'user_id', 'user_id');
    }

    /**
     * Tam ad
     */
    public function getFullNameAttribute(): string
    {
        return $this->user?->name ?? 'Unknown';
    }

    /**
     * Aktif mi?
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Kıdem yılı
     */
    public function getSeniorityYearsAttribute(): ?int
    {
        if (! $this->hire_date) {
            return null;
        }

        return $this->hire_date->diffInYears(now());
    }

    /**
     * Aktif personeller
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Departmana göre filtrele
     */
    public function scopeInDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }
}
