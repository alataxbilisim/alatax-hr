<?php

namespace App\Http\Requests\Workflow;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Services\ApprovalStepConditionEvaluator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', 'string', Rule::in(array_keys(ApprovalWorkflow::getEntityTypes()))],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'conditions' => ['nullable', 'array'],
            'escalation_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'steps' => ['required', 'array', 'min:1'],
        ], $this->stepRules());
    }

    /**
     * @return array<string, mixed>
     */
    protected function stepRules(): array
    {
        $approverTypes = array_keys(ApprovalStep::getApproverTypes());
        $ops = ApprovalStepConditionEvaluator::allowedOps();

        return [
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.approver_type' => ['required', 'string', Rule::in($approverTypes)],
            'steps.*.specific_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'steps.*.specific_role' => ['nullable', 'string', 'max:100'],
            'steps.*.is_required' => ['sometimes', 'boolean'],
            'steps.*.can_skip' => ['sometimes', 'boolean'],
            'steps.*.timeout_hours' => ['nullable', 'integer', 'min:1'],
            'steps.*.timeout_action' => ['nullable', 'string', Rule::in(['escalate', 'auto_approve', 'auto_reject'])],
            'steps.*.condition' => ['nullable', 'array'],
            'steps.*.condition.field' => ['nullable', 'string', Rule::in(ApprovalStepConditionEvaluator::allowedFields())],
            'steps.*.condition.op' => ['nullable', 'string', Rule::in($ops)],
            'steps.*.condition.operator' => ['nullable', 'string', Rule::in($ops)],
            'steps.*.condition.value' => ['nullable'],
            'steps.*.parallel_group' => ['nullable', 'integer', 'min:1', 'max:99'],
            'steps.*.completion_policy' => ['nullable', 'string', Rule::in(['all', 'any'])],
            'steps.*.escalation_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
