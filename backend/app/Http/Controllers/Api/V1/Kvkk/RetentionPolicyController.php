<?php

namespace App\Http\Controllers\Api\V1\Kvkk;

use App\Http\Controllers\Controller;
use App\Models\RetentionPolicy;
use App\Services\Kvkk\Retention\RetentionPolicyService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetentionPolicyController extends Controller
{
    use ApiResponse;

    public function __construct(private RetentionPolicyService $service) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = RetentionPolicy::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        return $this->paginated($rows, 'Saklama politikaları');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'data_category' => ['required', 'string', 'max:64'],
            'subject_type' => ['required', 'string', 'max:32'],
            'trigger_event' => ['required', 'string'],
            'retention_months' => ['required', 'integer', 'min:1'],
            'strategy' => ['nullable', 'string', 'in:anonymize,pseudonymize,hard_delete,archive'],
            'legal_basis_note' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        $row = $this->service->create((int) $request->user()->company_id, $request->user(), $data);

        return $this->success($row, 'Politika oluşturuldu', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $policy = RetentionPolicy::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'data_category' => ['sometimes', 'string', 'max:64'],
            'subject_type' => ['sometimes', 'string', 'max:32'],
            'trigger_event' => ['sometimes', 'string'],
            'retention_months' => ['sometimes', 'integer', 'min:1'],
            'strategy' => ['sometimes', 'string', 'in:anonymize,pseudonymize,hard_delete,archive'],
            'legal_basis_note' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
        ]);

        return $this->success($this->service->update($policy, $request->user(), $data), 'Politika güncellendi');
    }

    public function seedDrafts(Request $request): JsonResponse
    {
        $created = $this->service->seedDraftsForCompany((int) $request->user()->company_id);

        return $this->success(['count' => count($created), 'items' => $created], 'Taslak politikalar eklendi (pasif)');
    }
}
