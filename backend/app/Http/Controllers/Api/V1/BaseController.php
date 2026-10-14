<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Support\CompanyContext;
use App\Traits\ApiResponse;
use App\Traits\AppliesListSorting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * API V1 Base Controller
 * Tüm API controller'ları bu class'tan extend eder
 */
class BaseController extends Controller
{
    use ApiResponse, AppliesListSorting, AuthorizesRequests;

    /**
     * Aktif operasyonel şirket (CompanyContext; yoksa home company_id).
     */
    protected function getCompanyId(): ?int
    {
        if (CompanyContext::isBound()) {
            return CompanyContext::id();
        }

        return auth()->user()?->home_company_id;
    }

    /**
     * Kullanıcının SuperAdmin olup olmadığını kontrol et
     */
    protected function isSuperAdmin(): bool
    {
        return auth()->user()?->type === UserType::SuperAdmin;
    }

    /**
     * Kullanıcının firma admini olup olmadığını kontrol et
     */
    protected function isCompanyAdmin(): bool
    {
        return auth()->user()?->type === UserType::CompanyAdmin;
    }
}
