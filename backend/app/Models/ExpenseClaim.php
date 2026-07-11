<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ExpenseClaim extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    /** @var list<string> */
    protected array $auditMasked = [
        'payment_reference',
    ];

    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'description',
        'claim_number',
        'expense_date',
        'total_amount',
        'currency',
        'status',
        'rejection_reason',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'payment_method',
        'payment_reference',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'total_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_PAID = 'paid';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function approvalRecords(): MorphMany
    {
        return $this->morphMany(ApprovalRecord::class, 'approvable');
    }

    public function calculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('amount');
    }

    public static function generateClaimNumber(int $companyId): string
    {
        $year = now()->format('Y');
        $count = static::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('EXP-%s-%04d', $year, $count);
    }
}
