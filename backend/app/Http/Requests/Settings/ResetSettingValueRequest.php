<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ResetSettingValueRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:191'],
            'scope_type' => ['required', 'string', 'in:system,company,branch,department,user'],
            'scope_id' => ['nullable', 'integer', 'min:1'],
            'company_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
