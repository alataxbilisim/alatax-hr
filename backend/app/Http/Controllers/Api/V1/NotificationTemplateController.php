<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Notification\UpsertNotificationTemplateRequest;
use App\Services\Notification\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Stüdyo — bildirim şablon yönetimi (4C-2).
 */
class NotificationTemplateController extends BaseController
{
    public function __construct(
        protected NotificationTemplateService $templates,
    ) {}

    /**
     * GET /api/v1/notification-templates
     */
    public function index(): JsonResponse
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli.', 403);
        }

        return $this->success([
            'templates' => $this->templates->catalogForCompany($companyId),
        ]);
    }

    /**
     * PUT /api/v1/notification-templates/{eventKey}
     */
    public function upsert(UpsertNotificationTemplateRequest $request, string $eventKey): JsonResponse
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli.', 403);
        }

        try {
            $row = $this->templates->upsert(
                $companyId,
                $eventKey,
                $request->validated(),
                auth()->id()
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'event_key' => $row->event_key,
            'subject' => $row->subject,
            'body' => $row->body,
            'updated_at' => $row->updated_at?->toIso8601String(),
        ], 'Bildirim şablonu kaydedildi');
    }

    /**
     * DELETE /api/v1/notification-templates/{eventKey}
     */
    public function destroy(string $eventKey): JsonResponse
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli.', 403);
        }

        try {
            $deleted = $this->templates->clear($companyId, $eventKey);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        if (! $deleted) {
            return $this->error('Override bulunamadı', 404);
        }

        return $this->success(null, 'Şablon varsayılana döndürüldü');
    }
}
