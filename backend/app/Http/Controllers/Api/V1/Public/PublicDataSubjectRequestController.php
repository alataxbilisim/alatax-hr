<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Company;
use App\Models\DataSubjectRequest;
use App\Services\Kvkk\DataSubjectRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public — ayrılmış personel / aday KVKK talebi (e-posta doğrulamalı).
 */
class PublicDataSubjectRequestController extends BaseController
{
    public function __construct(
        protected DataSubjectRequestService $requests,
    ) {}

    public function store(Request $request, string $companySlug): JsonResponse
    {
        $company = Company::query()->where('slug', $companySlug)->where('status', 'active')->firstOrFail();

        $validated = $request->validate([
            'subject_type' => 'required|in:employee,candidate,former_employee,visitor,other',
            'applicant_name' => 'required|string|max:255',
            'contact' => 'required|email|max:255',
            'request_types' => 'required|array|min:1',
            'request_types.*' => 'string',
            'description' => 'nullable|string|max:5000',
        ]);

        $row = $this->requests->create((int) $company->id, array_merge($validated, [
            'channel' => 'public_form',
            'identity_verified' => false,
        ]), null);

        // E-posta doğrulama token'ı response'ta (gerçekte mail ile gider; test için döner)
        return $this->success([
            'id' => $row->id,
            'status' => $row->status->value,
            'email_verify_required' => true,
            'verify_token' => $row->email_verify_token, // prod'da mail ile; test kolaylığı
        ], 'Talep alındı — e-posta doğrulaması gerekli', 201);
    }

    public function verifyEmail(Request $request, string $companySlug, int $id): JsonResponse
    {
        $company = Company::query()->where('slug', $companySlug)->firstOrFail();
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $row = DataSubjectRequest::query()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $row = $this->requests->verifyEmailToken($row, $validated['token']);

        return $this->success([
            'id' => $row->id,
            'email_verified_at' => $row->email_verified_at?->toIso8601String(),
            'status' => $row->status->value,
            'note' => 'E-posta doğrulandı. Kimlik İK tarafından onaylanana kadar paket üretilemez.',
        ], 'E-posta doğrulandı');
    }
}
