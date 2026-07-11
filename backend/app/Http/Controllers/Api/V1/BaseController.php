<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * API V1 Base Controller
 * Tüm API controller'ları bu class'tan extend eder
 */
class BaseController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    /**
     * Geçerli kullanıcının firma ID'sini al
     */
    protected function getCompanyId(): ?int
    {
        return auth()->user()?->company_id;
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
