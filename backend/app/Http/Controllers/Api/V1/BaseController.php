<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

/**
 * API V1 Base Controller
 * Tüm API controller'ları bu class'tan extend eder
 */
class BaseController extends Controller
{
    use ApiResponse;

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
        return auth()->user()?->type === 'super_admin';
    }

    /**
     * Kullanıcının firma admini olup olmadığını kontrol et
     */
    protected function isCompanyAdmin(): bool
    {
        return auth()->user()?->type === 'company_admin';
    }
}

