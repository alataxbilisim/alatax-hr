<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'max_amount',
        'requires_receipt',
        'is_active',
    ];

    protected $casts = [
        'max_amount' => 'decimal:2',
        'requires_receipt' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function expenseItems(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }
}
