<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // yetki route middleware ile
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = auth()->user()?->company_id;

        return [
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('positions', 'code')->where(
                    fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($q) => $q->where('company_id', $companyId)
                ),
            ],
            'sgk_occupation_code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Pozisyon adı zorunludur.',
            'code.unique' => 'Bu pozisyon kodu zaten kullanılıyor.',
            'department_id.exists' => 'Seçilen departman geçersiz.',
        ];
    }
}
