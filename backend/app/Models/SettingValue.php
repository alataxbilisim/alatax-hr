<?php

namespace App\Models;

use App\Enums\SettingScopeType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Settings Registry değer satırı.
 * BelongsToCompany kullanılmaz: system satırlarında company_id null.
 */
class SettingValue extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'company_id',
        'scope_type',
        'scope_id',
        'key',
        'value',
        'updated_by',
    ];

    protected $casts = [
        'scope_type' => SettingScopeType::class,
        'value' => 'array',
        'scope_id' => 'integer',
        'company_id' => 'integer',
        'updated_by' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * JSONB sarmalayıcı — skaler değerler { "v": ... } olarak saklanır.
     */
    public function scalarValue(): mixed
    {
        $raw = $this->value;
        if (is_array($raw) && array_key_exists('v', $raw) && count($raw) === 1) {
            return $raw['v'];
        }

        return $raw;
    }

    public static function wrapScalar(mixed $value): array
    {
        return ['v' => $value];
    }
}
