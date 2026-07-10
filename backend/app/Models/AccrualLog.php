<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccrualLog extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'leave_type_id',
        'accrual_policy_id',
        'leave_balance_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'effective_date',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'effective_date' => 'date',
    ];

    // Log tipleri
    const TYPE_ACCRUAL = 'accrual';

    const TYPE_USAGE = 'usage';

    const TYPE_ADJUSTMENT = 'adjustment';

    const TYPE_CARRYOVER = 'carryover';

    const TYPE_EXPIRY = 'expiry';

    const TYPE_ENCASHMENT = 'encashment';

    const TYPE_INITIAL_GRANT = 'initial_grant';

    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_ACCRUAL => 'Hakediş',
            self::TYPE_USAGE => 'Kullanım',
            self::TYPE_ADJUSTMENT => 'Düzeltme',
            self::TYPE_CARRYOVER => 'Devir',
            self::TYPE_EXPIRY => 'Süre Dolumu',
            self::TYPE_ENCASHMENT => 'Nakde Çevirme',
            self::TYPE_INITIAL_GRANT => 'İlk Atama',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function accrualPolicy(): BelongsTo
    {
        return $this->belongsTo(AccrualPolicy::class);
    }

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForLeaveType($query, int $leaveTypeId)
    {
        return $query->where('leave_type_id', $leaveTypeId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Yeni log kaydı oluştur
     */
    public static function createLog(
        int $companyId,
        int $userId,
        int $leaveTypeId,
        string $type,
        float $amount,
        float $balanceBefore,
        ?string $description = null,
        ?Model $reference = null,
        ?int $accrualPolicyId = null,
        ?int $leaveBalanceId = null
    ): self {
        return self::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'leave_type_id' => $leaveTypeId,
            'accrual_policy_id' => $accrualPolicyId,
            'leave_balance_id' => $leaveBalanceId,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore + $amount,
            'description' => $description,
            'effective_date' => now()->toDateString(),
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
            'created_by' => auth()->id(),
        ]);
    }
}
