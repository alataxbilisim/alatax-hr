<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRead extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'announcement_id',
        'user_id',
        'employee_id',
        'read_at',
        'acknowledged',
        'acknowledged_at',
    ];

    protected $casts = [
        'acknowledged' => 'boolean',
        'read_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * Duyuru
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * Kullanıcı
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Onayla
     */
    public function acknowledge(): void
    {
        $this->update([
            'acknowledged' => true,
            'acknowledged_at' => now(),
        ]);
    }
}
