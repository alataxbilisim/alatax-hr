<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertApplicationToEmployeeRequest extends FormRequest
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
        $companyId = $this->user()?->company_id;

        return [
            'employee_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_code')->where(
                    fn ($q) => $q->where('company_id', $companyId)
                ),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($q) => $q->where('company_id', $companyId)
                ),
            ],
            'hire_date' => ['nullable', 'date'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($q) => $q->where('company_id', $companyId)
                ),
            ],
        ];
    }
}
