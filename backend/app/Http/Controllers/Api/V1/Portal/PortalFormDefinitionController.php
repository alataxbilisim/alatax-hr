<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\CustomFieldDefinition;
use App\Services\FormDefinitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Portal Form Engine okuma — yalnız leave_request / expense (yazma yok).
 */
class PortalFormDefinitionController extends BaseController
{
    /** @var list<string> */
    private const PORTAL_ENTITIES = [
        CustomFieldDefinition::ENTITY_LEAVE_REQUEST,
        CustomFieldDefinition::ENTITY_EXPENSE,
    ];

    public function __construct(
        protected FormDefinitionService $formDefinitions,
    ) {}

    public function show(string $entityType): JsonResponse
    {
        if (! in_array($entityType, self::PORTAL_ENTITIES, true)) {
            throw ValidationException::withMessages([
                'entity_type' => ['Portal form tanımları: leave_request, expense.'],
            ]);
        }

        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return $this->error('Firma bağlamı gerekli.', 403);
        }

        $data = $this->formDefinitions->getForEntity($entityType, $companyId);

        return $this->success($data);
    }
}
