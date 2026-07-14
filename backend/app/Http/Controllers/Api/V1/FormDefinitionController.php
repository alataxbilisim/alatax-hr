<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Services\FormDefinitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormDefinitionController extends BaseController
{
    public function __construct(
        protected FormDefinitionService $formDefinitions
    ) {}

    /**
     * GET /api/v1/form-definitions/{entityType}
     */
    public function show(string $entityType): JsonResponse
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli.', 403);
        }

        $data = $this->formDefinitions->getForEntity($entityType, $companyId);

        return $this->success($data);
    }

    /**
     * PUT /api/v1/form-definitions/{entityType}
     */
    public function update(Request $request, string $entityType): JsonResponse
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli.', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'layout' => 'sometimes|array',
            'layout.sections' => 'sometimes|array',
            'fields' => 'sometimes|array',
            'fields.*.id' => 'nullable|integer',
            'fields.*.system_key' => 'nullable|string|max:100',
            'fields.*.field_key' => 'nullable|string|max:100',
            'fields.*.is_system' => 'nullable|boolean',
            'fields.*.label_override' => 'nullable|string|max:255',
            'fields.*.field_label' => 'nullable|string|max:255',
            'fields.*.is_hidden' => 'nullable|boolean',
            'fields.*.is_required' => 'nullable|boolean',
            'fields.*.is_required_override' => 'nullable|boolean',
            'fields.*.sort_order' => 'nullable|integer|min:0',
            'fields.*.field_permission' => ['nullable', Rule::in(['readonly', 'hidden'])],
            'fields.*.delete' => 'nullable|boolean',
        ]);

        $data = $this->formDefinitions->updateForEntity(
            $entityType,
            $companyId,
            $validated,
            auth()->id()
        );

        return $this->success($data, 'Form tanımı güncellendi');
    }
}
