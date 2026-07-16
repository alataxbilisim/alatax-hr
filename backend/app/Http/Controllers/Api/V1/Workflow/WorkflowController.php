<?php

namespace App\Http\Controllers\Api\V1\Workflow;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Workflow\StoreWorkflowRequest;
use App\Http\Requests\Workflow\UpdateWorkflowRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Services\ApprovalStepConditionEvaluator;
use App\Services\Workflow\WorkflowManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WorkflowController extends BaseController
{
    public function __construct(
        protected WorkflowManagementService $workflows,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalWorkflow::class);

        $list = $this->workflows->listForCompany((int) $this->getCompanyId(), [
            'entity_type' => $request->query('entity_type'),
            'is_active' => $request->query('is_active'),
        ]);

        return $this->success($list, 'Onay akışları listelendi');
    }

    public function show(int $id): JsonResponse
    {
        $workflow = $this->workflows->findForCompany((int) $this->getCompanyId(), $id);
        $this->authorize('view', $workflow);

        return $this->success($workflow, 'Onay akışı detayı');
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $this->authorize('create', ApprovalWorkflow::class);

        $workflow = $this->workflows->create(
            (int) $this->getCompanyId(),
            $request->user(),
            $request->validated()
        );

        return $this->created($workflow, 'Onay akışı oluşturuldu');
    }

    public function update(UpdateWorkflowRequest $request, int $id): JsonResponse
    {
        $workflow = $this->workflows->findForCompany((int) $this->getCompanyId(), $id);
        $this->authorize('update', $workflow);

        try {
            $updated = $this->workflows->update($workflow, $request->user(), $request->validated());
        } catch (RuntimeException $e) {
            if ($e->getCode() === 409) {
                return $this->error($e->getMessage(), 409);
            }

            throw $e;
        }

        return $this->success($updated, 'Onay akışı güncellendi');
    }

    public function destroy(int $id): JsonResponse
    {
        $workflow = $this->workflows->findForCompany((int) $this->getCompanyId(), $id);
        $this->authorize('delete', $workflow);

        try {
            $this->workflows->delete($workflow);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 409) {
                return $this->error($e->getMessage(), 409);
            }

            throw $e;
        }

        return $this->success(null, 'Onay akışı silindi');
    }

    /**
     * Varsayılan izin akışını oluştur / güvenceye al (CLI gerektirmez).
     */
    public function seedDefaultLeave(): JsonResponse
    {
        $this->authorize('create', ApprovalWorkflow::class);

        $workflow = $this->workflows->seedDefaultLeave((int) $this->getCompanyId());

        return $this->success($workflow, 'Varsayılan izin akışı hazır');
    }

    public function getByEntityType(string $entityType): JsonResponse
    {
        $this->authorize('viewAny', ApprovalWorkflow::class);

        $workflows = $this->workflows->listForCompany((int) $this->getCompanyId(), [
            'entity_type' => $entityType,
            'is_active' => true,
        ]);

        return $this->success($workflows, 'Onay akışları listelendi');
    }

    public function getEntityTypes(): JsonResponse
    {
        return $this->success(ApprovalWorkflow::getEntityTypes(), 'Entity tipleri');
    }

    public function getApproverTypes(): JsonResponse
    {
        return $this->success(ApprovalStep::getApproverTypes(), 'Onaylayıcı tipleri');
    }

    /**
     * B3 koşul whitelist — Stüdyo UI için.
     */
    public function getConditionMeta(): JsonResponse
    {
        return $this->success([
            'fields' => ApprovalStepConditionEvaluator::allowedFields(),
            'ops' => ApprovalStepConditionEvaluator::allowedOps(),
        ], 'Koşul meta');
    }
}
