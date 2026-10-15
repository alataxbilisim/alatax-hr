<?php

namespace App\Rules;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Tenant tablolarında `exists` — kayıt yalnız aktif şirket bağlamındaysa geçer.
 * `allowGlobal`: company_id NULL olan sistem satırları (ör. sistem form tanımları) da kabul edilir.
 *
 * String takma adı: `company_exists:tablo[,kolon][,global]`.
 */
class ExistsInCompany implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $column = 'id',
        private readonly bool $allowGlobal = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($this->table, $value, $this->column, $this->allowGlobal)) {
            $fail('validation.exists')->translate();
        }
    }

    public static function passes(string $table, mixed $value, string $column = 'id', bool $allowGlobal = false): bool
    {
        if (! is_scalar($value) || $value === '') {
            return false;
        }

        $companyId = self::companyId();
        if ($companyId === null) {
            return false;
        }

        return DB::table($table)
            ->where($column, $value)
            ->where(function ($query) use ($companyId, $allowGlobal): void {
                $query->where('company_id', $companyId);
                if ($allowGlobal) {
                    $query->orWhereNull('company_id');
                }
            })
            ->exists();
    }

    /**
     * BaseController::getCompanyId ile aynı çözüm: bağlam, yoksa oturumdaki home şirket.
     */
    public static function companyId(): ?int
    {
        if (CompanyContext::isBound()) {
            return CompanyContext::id();
        }

        $homeCompanyId = auth()->user()?->home_company_id;

        return $homeCompanyId !== null ? (int) $homeCompanyId : null;
    }
}
