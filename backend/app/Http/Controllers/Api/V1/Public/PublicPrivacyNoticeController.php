<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Kvkk\PrivacyNoticeService;
use Illuminate\Http\JsonResponse;

/**
 * public: aday aydınlatma metni (aktif versiyon).
 */
class PublicPrivacyNoticeController extends Controller
{
    public function __construct(
        protected PrivacyNoticeService $notices,
    ) {}

    public function show(string $companySlug): JsonResponse
    {
        $company = Company::query()->where('slug', $companySlug)->first();
        if ($company === null) {
            return response()->json([
                'success' => false,
                'message' => 'Firma bulunamadı',
                'data' => null,
                'errors' => null,
                'timestamp' => now()->toDateTimeString(),
            ], 404);
        }

        $notice = $this->notices->activeFor((int) $company->id, 'candidate');

        return response()->json([
            'success' => true,
            'message' => $notice ? 'Aydınlatma metni' : 'Aktif aydınlatma yok',
            'data' => $notice ? [
                'id' => $notice->id,
                'version' => $notice->version,
                'title' => $notice->title,
                'body' => $notice->body,
                'effective_from' => $notice->effective_from,
            ] : null,
            'errors' => null,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
