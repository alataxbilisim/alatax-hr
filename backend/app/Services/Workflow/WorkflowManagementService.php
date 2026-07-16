<?php

namespace App\Services\Workflow;

use App\Models\ActivityLog;
use App\Models\ApprovalInstance;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Services\DefaultLeaveApprovalWorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Onay akışı yapılandırma CRUD (motor runtime'a dokunmaz).
 * Aktif instance varken adım senkronu 409.
 */
class WorkflowManagementService
{
    public function __construct(
        protected DefaultLeaveApprovalWorkflowService $defaultLeaveWorkflows,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, ApprovalWorkflow>
     */
    public function listForCompany(int $companyId, array $filters = [])
    {
        $query = ApprovalWorkflow::query()
            ->where('company_id', $companyId)
            ->withCount('steps')
            ->with(['steps' => fn ($q) => $q->orderBy('step_order')]);

        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('entity_type')->orderBy('name')->get();
    }

    public function findForCompany(int $companyId, int $id): ApprovalWorkflow
    {
        return ApprovalWorkflow::query()
            ->where('company_id', $companyId)
            ->with(['steps' => fn ($q) => $q->orderBy('step_order')])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $companyId, User $actor, array $data): ApprovalWorkflow
    {
        return DB::transaction(function () use ($companyId, $actor, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefaultFlag($companyId, $data['entity_type']);
            }

            $workflow = ApprovalWorkflow::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'entity_type' => $data['entity_type'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_default' => $data['is_default'] ?? false,
                'conditions' => $data['conditions'] ?? null,
                'escalation_days' => $data['escalation_days'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->syncSteps($workflow, $data['steps'] ?? []);

            ActivityLog::log('create', $workflow, 'Yeni onay akışı oluşturuldu: '.$workflow->name);

            return $workflow->load(['steps' => fn ($q) => $q->orderBy('step_order')]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ApprovalWorkflow $workflow, User $actor, array $data): ApprovalWorkflow
    {
        $hasSteps = array_key_exists('steps', $data);

        if ($hasSteps && $this->hasOpenInstances($workflow)) {
            throw new RuntimeException(
                'Bu akışta açık onay örneği var; adımlar değiştirilemez. Önce sonuçlandırın.',
                409
            );
        }

        return DB::transaction(function () use ($workflow, $actor, $data, $hasSteps) {
            $old = $workflow->toArray();

            if (! empty($data['is_default']) && ! $workflow->is_default) {
                $this->clearDefaultFlag(
                    (int) $workflow->company_id,
                    $workflow->entity_type,
                    (int) $workflow->id
                );
            }

            $workflow->update([
                'name' => $data['name'] ?? $workflow->name,
                'description' => array_key_exists('description', $data)
                    ? $data['description']
                    : $workflow->description,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $workflow->is_active,
                'is_default' => array_key_exists('is_default', $data)
                    ? (bool) $data['is_default']
                    : $workflow->is_default,
                'conditions' => array_key_exists('conditions', $data)
                    ? $data['conditions']
                    : $workflow->conditions,
                'escalation_days' => array_key_exists('escalation_days', $data)
                    ? $data['escalation_days']
                    : $workflow->escalation_days,
                'updated_by' => $actor->id,
            ]);

            if ($hasSteps) {
                $this->syncSteps($workflow, $data['steps'] ?? []);
            }

            ActivityLog::log(
                'update',
                $workflow,
                'Onay akışı güncellendi'.($hasSteps ? ' (adımlar senkron)' : ''),
                $old,
                $workflow->fresh()->toArray()
            );

            return $workflow->fresh()->load(['steps' => fn ($q) => $q->orderBy('step_order')]);
        });
    }

    public function delete(ApprovalWorkflow $workflow): void
    {
        if ($workflow->instances()->exists()) {
            throw new RuntimeException(
                'Bu akışa bağlı onay örneği olduğu için silinemez.',
                409
            );
        }

        $name = $workflow->name;
        $workflow->delete();

        ActivityLog::log('delete', $workflow, 'Onay akışı silindi: '.$name);
    }

    public function seedDefaultLeave(int $companyId): ApprovalWorkflow
    {
        $workflow = $this->defaultLeaveWorkflows->ensureForCompany($companyId);

        return $workflow->load(['steps' => fn ($q) => $q->orderBy('step_order')]);
    }

    public function hasOpenInstances(ApprovalWorkflow $workflow): bool
    {
        return $workflow->instances()
            ->whereIn('status', [
                ApprovalInstance::STATUS_PENDING,
                ApprovalInstance::STATUS_IN_PROGRESS,
            ])
            ->exists();
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    protected function syncSteps(ApprovalWorkflow $workflow, array $steps): void
    {
        $keepIds = [];

        foreach (array_values($steps) as $index => $stepData) {
            $payload = $this->normalizeStepPayload($stepData, $index + 1);
            $stepId = isset($stepData['id']) ? (int) $stepData['id'] : null;

            if ($stepId) {
                $existing = $workflow->steps()->whereKey($stepId)->first();
                if ($existing) {
                    $existing->update($payload);
                    $keepIds[] = $existing->id;

                    continue;
                }
            }

            $created = ApprovalStep::create(array_merge($payload, [
                'approval_workflow_id' => $workflow->id,
            ]));
            $keepIds[] = $created->id;
        }

        $workflow->steps()
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->when($keepIds === [], fn ($q) => $q)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $stepData
     * @return array<string, mixed>
     */
    protected function normalizeStepPayload(array $stepData, int $order): array
    {
        $parallelGroup = $stepData['parallel_group'] ?? null;
        if ($parallelGroup === '' || $parallelGroup === null) {
            $parallelGroup = null;
        } else {
            $parallelGroup = (int) $parallelGroup;
        }

        return [
            'step_order' => $order,
            'name' => $stepData['name'],
            'approver_type' => $stepData['approver_type'],
            'specific_user_id' => $stepData['specific_user_id'] ?? null,
            'specific_role' => $stepData['specific_role'] ?? null,
            'is_required' => $stepData['is_required'] ?? true,
            'can_skip' => $stepData['can_skip'] ?? false,
            'timeout_hours' => $stepData['timeout_hours'] ?? null,
            'timeout_action' => $stepData['timeout_action'] ?? null,
            'condition' => $stepData['condition'] ?? null,
            'parallel_group' => $parallelGroup,
            'completion_policy' => $stepData['completion_policy'] ?? ApprovalStep::COMPLETION_ALL,
            'escalation_days' => $stepData['escalation_days'] ?? null,
        ];
    }

    protected function clearDefaultFlag(int $companyId, string $entityType, ?int $exceptId = null): void
    {
        $query = ApprovalWorkflow::query()
            ->where('company_id', $companyId)
            ->where('entity_type', $entityType);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }
}
