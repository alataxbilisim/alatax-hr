<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    protected $fillable = [
        'expense_claim_id',
        'expense_category_id',
        'description',
        'item_date',
        'amount',
        'currency',
        'receipt_path',
        'receipt_number',
        'vendor_name',
        'notes',
    ];

    protected $casts = [
        'item_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function expenseClaim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
