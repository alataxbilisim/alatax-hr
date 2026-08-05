<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Enums\KvkkConsentType;
use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ConsentRecord;
use App\Services\Kvkk\ConsentRecordService;
use App\Services\Kvkk\PrivacyNoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Portal — aydınlatma görüntüleme / onay (zorunlu değil) + açık rıza geri çekme.
 */
class PortalPrivacyController extends BaseController
{
    public function __construct(
        protected PrivacyNoticeService $notices,
        protected ConsentRecordService $consents,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $user->home_company_id;
        $notice = $this->notices->activeFor($companyId, 'employee');

        $ack = null;
        if ($notice) {
            $ack = ConsentRecord::query()
                ->where('company_id', $companyId)
                ->where('subject_type', 'employee')
                ->where('subject_id', $user->id)
                ->where('notice_id', $notice->id)
                ->where('consent_type', KvkkConsentType::NoticeRead->value)
                ->orderByDesc('id')
                ->first();
        }

        $myConsents = ConsentRecord::query()
            ->where('company_id', $companyId)
            ->where('subject_type', 'employee')
            ->where('subject_id', $user->id)
            ->whereNull('withdrawn_at')
            ->where('granted', true)
            ->with('notice:id,title,version,audience')
            ->orderByDesc('id')
            ->get();

        return $this->success([
            'active_notice' => $notice,
            'latest_acknowledgment' => $ack,
            // Onaylamadan devam edilebilir — zorlamak rızayı sakatlar
            'can_continue_without_ack' => true,
            'needs_attention' => $notice !== null && ($ack === null || ! $ack->granted || $ack->withdrawn_at !== null),
            'my_consents' => $myConsents,
        ]);
    }

    /**
     * Aydınlatma: granted=true (okudum) veya granted=false (görüntülendi / onaylanmadı).
     */
    public function acknowledge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notice_id' => ['required', 'integer'],
            'granted' => ['required', 'boolean'],
        ]);
        $user = $request->user();
        $row = $this->consents->record((int) $user->home_company_id, [
            'subject_type' => 'employee',
            'subject_id' => $user->id,
            'notice_id' => $validated['notice_id'],
            'consent_type' => KvkkConsentType::NoticeRead->value,
            'granted' => $validated['granted'],
            'source' => 'portal',
            'evidence' => ['action' => $validated['granted'] ? 'acknowledged' : 'viewed_declined'],
        ], $request);

        return $this->success($row, 'Aydınlatma kaydı alındı');
    }

    public function withdraw(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        try {
            $row = $this->consents->withdrawOwn((int) $user->home_company_id, (int) $user->id, $id);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success($row, 'Rıza geri çekildi');
    }
}
