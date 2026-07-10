<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'user_id',
        'assigned_date',
        'return_date',
        'notes',
        'condition_at_assignment',
        'condition_at_return',
        'assigned_by',
        'returned_to',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'return_date' => 'date',
    ];

    /**
     * Varlık
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Zimmetli kullanıcı
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Zimmet veren
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * İade alan
     */
    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    /**
     * Aktif zimmetler
     */
    public function scopeActive($query)
    {
        return $query->whereNull('return_date');
    }

    /**
     * İade edilmiş zimmetler
     */
    public function scopeReturned($query)
    {
        return $query->whereNotNull('return_date');
    }
}

