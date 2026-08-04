<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kullanıcı ↔ şirket membership (G1).
 * role_id şimdilik NULL — gelecek için kolon açık.
 */
class CompanyUser extends Model
{
    public $timestamps = false;

    protected $table = 'company_user';

    protected $fillable = [
        'user_id',
        'company_id',
        'role_id',
        'is_default',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CompanyUser $row): void {
            if ($row->created_at === null) {
                $row->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
