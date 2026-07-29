<?php

namespace App\Http\Controllers\Api\V1\Kvkk;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\PrivacyNotice;
use App\Services\Kvkk\PrivacyNoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrivacyNoticeController extends BaseController
{
    public function __construct(
        protected PrivacyNoticeService $notices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $audience = $request->query('audience');
        $page = $this->notices->listFor(
            (int) $this->getCompanyId(),
            is_string($audience) ? $audience : null,
            $request->integer('per_page', 50)
        );

        return $this->paginated($page, 'Aydınlatma metinleri');
    }

    public function active(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(['employee', 'candidate', 'visitor', 'contractor'])],
        ]);
        $notice = $this->notices->activeFor((int) $this->getCompanyId(), $validated['audience']);

        return $this->success($notice, $notice ? 'Aktif aydınlatma' : 'Aktif aydınlatma yok');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(['employee', 'candidate', 'visitor', 'contractor'])],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'effective_from' => ['nullable', 'date'],
        ]);
        $notice = $this->notices->createDraft((int) $this->getCompanyId(), $validated);

        return $this->success($notice, 'Taslak oluşturuldu', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $notice = PrivacyNotice::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:100000'],
            'effective_from' => ['nullable', 'date'],
        ]);
        try {
            $notice = $this->notices->updateDraft($notice, $validated);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success($notice, 'Taslak güncellendi');
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $notice = PrivacyNotice::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);
        try {
            $notice = $this->notices->publish($notice, $request->user());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success($notice, 'Aydınlatma yayınlandı');
    }

    public function destroy(int $id): JsonResponse
    {
        $notice = PrivacyNotice::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);
        try {
            $this->notices->deleteDraft($notice);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success(null, 'Taslak silindi');
    }
}
