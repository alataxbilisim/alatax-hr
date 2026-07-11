<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use BelongsToCompany;

    const UPDATED_AT = null; // Sadece created_at kullan

    protected $fillable = [
        'company_id',
        'user_id',
        'user_name',
        'action',
        'model_type',
        'model_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'is_successful',
        'error_message',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'is_successful' => 'boolean',
    ];

    /**
     * Kullanıcı ilişkisi
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * İlişkili model (polymorphic)
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * Log oluştur (statik helper)
     */
    public static function log(
        string $action,
        ?Model $model = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        bool $isSuccessful = true,
        ?string $errorMessage = null
    ): self {
        $user = auth()->user();
        $request = request();

        // company_id öncelik: 1) Company modeli → id, 2) model.company_id attribute, 3) auth user
        $companyId = null;
        if ($model instanceof Company) {
            $companyId = $model->id;
        } elseif ($model !== null) {
            $attrs = $model->getAttributes();
            if (array_key_exists('company_id', $attrs) && $attrs['company_id'] !== null) {
                $companyId = $attrs['company_id'];
            } elseif ($model->getAttribute('company_id') !== null) {
                $companyId = $model->getAttribute('company_id');
            }
        }
        if ($companyId === null) {
            $companyId = $user?->company_id;
        }

        return static::create([
            'company_id' => $companyId,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'model_type' => $model ? $model->getMorphClass() : null,
            'model_id' => $model?->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'is_successful' => $isSuccessful,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Belirli bir modelin loglarını getir
     */
    public function scopeForModel($query, Model $model)
    {
        return $query->where('model_type', get_class($model))
            ->where('model_id', $model->id);
    }

    /**
     * Belirli bir action'ın loglarını getir
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Başarılı işlemler
     */
    public function scopeSuccessful($query)
    {
        return $query->where('is_successful', true);
    }

    /**
     * Başarısız işlemler
     */
    public function scopeFailed($query)
    {
        return $query->where('is_successful', false);
    }
}
