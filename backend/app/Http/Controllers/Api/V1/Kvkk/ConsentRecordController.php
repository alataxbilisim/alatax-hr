<?php

namespace App\Http\Controllers\Api\V1\Kvkk;

use App\Enums\KvkkConsentType;
use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ConsentRecord;
use App\Services\Kvkk\ConsentRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConsentRecordController extends BaseController
{
    public function __construct(
        protected ConsentRecordService $consents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'subject_type' => $request->query('subject_type'),
            'consent_type' => $request->query('consent_type'),
            'granted' => $request->has('granted') ? $request->boolean('granted') : null,
        ];
        $page = $this->consents->listFor(
            (int) $this->getCompanyId(),
            array_filter($filters, static fn ($v) => $v !== null && $v !== ''),
            $request->integer('per_page', 50)
        );

        return $this->paginated($page, 'Rıza kayıtları');
    }

    public function missing(): JsonResponse
    {
        $list = $this->consents->missingNoticeAcknowledgments((int) $this->getCompanyId());

        return $this->success($list, 'Eksik aydınlatma onayları');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(['employee', 'candidate', 'visitor'])],
            'subject_id' => ['required', 'integer'],
            'notice_id' => ['nullable', 'integer'],
            'consent_type' => ['required', Rule::in(KvkkConsentType::values())],
            'granted' => ['required', 'boolean'],
            'source' => ['required', Rule::in(['portal', 'public_form', 'admin', 'import'])],
            'evidence' => ['nullable', 'array'],
        ]);

        $row = $this->consents->record((int) $this->getCompanyId(), $validated, $request);

        return $this->success($row, 'Rıza kaydı oluşturuldu', 201);
    }

    public function withdraw(int $id): JsonResponse
    {
        $record = ConsentRecord::query()
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);
        try {
            $row = $this->consents->withdraw($record);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success($row, 'Rıza geri çekildi');
    }
}
