<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
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
        $companyId = auth()->user()?->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('expense_categories', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'requires_receipt' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
