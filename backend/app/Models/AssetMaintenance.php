<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenance extends Model
{
    use HasFactory;

    protected $table = 'asset_maintenance';

    protected $fillable = [
        'asset_id',
        'type',
        'title',
        'description',
        'scheduled_date',
        'completed_date',
        'cost',
        'vendor',
        'status',
        'resolution',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'cost' => 'decimal:2',
    ];

    /**
     * Varlık
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Oluşturan kullanıcı
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Planlanmış bakımlar
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Tamamlanmış bakımlar
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Yaklaşan bakımlar
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')
            ->whereNotNull('scheduled_date')
            ->where('scheduled_date', '>=', now())
            ->orderBy('scheduled_date');
    }
}
