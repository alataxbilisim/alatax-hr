<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ImportSettingsProfileRequest extends FormRequest
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
            'company_id' => ['nullable', 'integer', 'min:1'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.key' => ['required', 'string', 'max:191'],
            'values.*.value' => ['present'],
        ];
    }
}
