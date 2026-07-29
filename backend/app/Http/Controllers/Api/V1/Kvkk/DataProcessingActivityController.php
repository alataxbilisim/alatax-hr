<?php

namespace App\Http\Controllers\Api\V1\Kvkk;

use App\Enums\KvkkLegalBasis;
use App\Http\Controllers\Api\V1\BaseController;
use App\Models\DataProcessingActivity;
use App\Services\Kvkk\DataProcessingActivityService;
use App\Services\Kvkk\KvkkDataCategoryCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataProcessingActivityController extends BaseController
{
    public function __construct(
        protected DataProcessingActivityService $activities,
    ) {}

    public function categories(): JsonResponse
    {
        return $this->success(KvkkDataCategoryCatalog::all(), 'Veri kategorileri (D1e hassasiyet)');
    }

    public function index(Request $request): JsonResponse
    {
        $page = $this->activities->listFor(
            (int) $this->getCompanyId(),
            $request->integer('per_page', 50)
        );

        return $this->paginated($page, 'Veri işleme envanteri');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'data_categories' => ['nullable', 'array'],
            'data_categories.*' => ['string'],
            'purpose' => ['nullable', 'string', 'max:10000'],
            'legal_basis' => ['required', Rule::in(KvkkLegalBasis::values())],
            'data_subject_group' => ['required', Rule::in(['employee', 'candidate', 'visitor', 'contractor'])],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['string', 'max:255'],
            'retention_period_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'transfer_abroad' => ['sometimes', 'boolean'],
            'transfer_abroad_note' => ['nullable', 'string', 'max:5000'],
            'security_measures' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $row = $this->activities->create((int) $this->getCompanyId(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        }

        return $this->success($row, 'Faaliyet oluşturuldu', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $activity = DataProcessingActivity::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'data_categories' => ['nullable', 'array'],
            'data_categories.*' => ['string'],
            'purpose' => ['nullable', 'string', 'max:10000'],
            'legal_basis' => ['sometimes', Rule::in(KvkkLegalBasis::values())],
            'data_subject_group' => ['sometimes', Rule::in(['employee', 'candidate', 'visitor', 'contractor'])],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['string', 'max:255'],
            'retention_period_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'transfer_abroad' => ['sometimes', 'boolean'],
            'transfer_abroad_note' => ['nullable', 'string', 'max:5000'],
            'security_measures' => ['nullable', 'string', 'max:10000'],
        ]);

        $row = $this->activities->update($activity, $validated);

        return $this->success($row, 'Faaliyet güncellendi');
    }

    public function destroy(int $id): JsonResponse
    {
        $activity = DataProcessingActivity::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);
        $this->activities->delete($activity);

        return $this->success(null, 'Faaliyet silindi');
    }

    public function export(): StreamedResponse
    {
        return $this->activities->exportExcel((int) $this->getCompanyId());
    }
}
