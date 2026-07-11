<?php

namespace App\Traits;

use App\Enums\UserType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenant modeller için trait
 * Bu trait'i kullanan modeller otomatik olarak company_id'ye göre filtrelenir
 */
trait BelongsToCompany
{
    /**
     * Boot method - Global scope ekler
     */
    protected static function bootBelongsToCompany(): void
    {
        // Yeni kayıt oluşturulurken otomatik company_id ata
        static::creating(function ($model) {
            if (auth()->check() && ! $model->company_id) {
                $user = auth()->user();
                // SuperAdmin değilse company_id ata
                if ($user->type !== UserType::SuperAdmin && $user->company_id) {
                    $model->company_id = $user->company_id;
                }
            }
        });

        // Global scope: SuperAdmin hariç her kullanıcı sadece kendi firmasının verilerini görür
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();

                // SuperAdmin tüm verileri görebilir
                if ($user->type === UserType::SuperAdmin) {
                    return;
                }

                // Diğer kullanıcılar sadece kendi firmalarının verilerini görür
                if ($user->company_id) {
                    $builder->where($builder->getModel()->getTable().'.company_id', $user->company_id);
                }
            }
        });
    }

    /**
     * Firma ilişkisi
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Belirli bir firmaya ait kayıtları getir (SuperAdmin için)
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope('company')->where('company_id', $companyId);
    }

    /**
     * Global scope olmadan sorgula
     */
    public function scopeWithoutCompanyScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('company');
    }
}
