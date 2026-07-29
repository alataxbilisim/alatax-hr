<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && ($this->user()->can('settings.values.edit') || $this->user()->isSuperAdmin());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope_type' => ['required', 'string', 'in:system,company,branch,department,user'],
            'scope_id' => ['nullable', 'integer', 'min:1'],
            'company_id' => ['nullable', 'integer', 'min:1'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.key' => ['required', 'string', 'max:191'],
            'values.*.value' => ['present'],
        ];
    }
}
