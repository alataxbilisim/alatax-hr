<?php

namespace App\Http\Requests\Workflow;

use App\Models\ApprovalStep;
use App\Services\ApprovalStepConditionEvaluator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowRequest extends FormRequest
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
        $approverTypes = array_keys(ApprovalStep::getApproverTypes());
        $ops = ApprovalStepConditionEvaluator::allowedOps();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'conditions' => ['nullable', 'array'],
            'escalation_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'steps' => ['sometimes', 'required', 'array', 'min:1'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.name' => ['required_with:steps', 'string', 'max:255'],
            'steps.*.approver_type' => ['required_with:steps', 'string', Rule::in($approverTypes)],
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
