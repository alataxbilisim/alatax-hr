<?php

namespace App\Rules;

use App\Services\CompanyContextService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Kullanıcı kimliği yalnız aktif şirket bağlamında üyeliği (company_user) varsa geçer.
 * `activeOnly`: ayrıca hesap aktif olmalı (ör. vekil ataması).
 *
 * String takma adı: `company_member` / `company_member:active`.
 */
class CompanyMember implements ValidationRule
{
    public function __construct(
        private readonly bool $activeOnly = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->activeOnly)) {
            $fail('validation.company_member')->translate();
        }
    }

    public static function passes(mixed $value, bool $activeOnly = false): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        $companyId = ExistsInCompany::companyId();
        if ($companyId === null) {
            return false;
        }

        return app(CompanyContextService::class)->isMember((int) $value, $companyId, $activeOnly);
    }
}
