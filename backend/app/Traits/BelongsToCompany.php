<?php

namespace App\Traits;

use App\Enums\UserType;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenant modeller için trait.
 * Global scope hâlâ "= tek company_id"; kaynak = CompanyContext (yoksa user.company_id).
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if ($model->company_id) {
                return;
            }

            $companyId = static::resolveActiveCompanyId();
            if ($companyId !== null) {
                $model->company_id = $companyId;
            }
        });

        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = static::resolveActiveCompanyId();
            if ($companyId === null) {
                return;
            }

            $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
        });
    }

    /**
     * Aktif operasyonel şirket: CompanyContext → auth user (SuperAdmin hariç).
     * Auth yok + bağlam yok → null (scope uygulanmaz; job'lar CompanyContext::run kullanır).
     */
    protected static function resolveActiveCompanyId(): ?int
    {
        if (CompanyContext::isBound()) {
            return CompanyContext::id();
        }

        if (! auth()->check()) {
            return null;
        }

        $user = auth()->user();
        if ($user === null || $user->type === UserType::SuperAdmin) {
            return null;
        }

        return $user->home_company_id ? (int) $user->home_company_id : null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope('company')->where('company_id', $companyId);
    }

    public function scopeWithoutCompanyScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('company');
    }
}
