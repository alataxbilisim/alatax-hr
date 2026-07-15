<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobApplicationRequest extends FormRequest
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
        return [
            'job_position_id' => ['required', 'integer', 'exists:job_positions,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'source' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'consent_kvkk' => ['required', 'accepted'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'form_data' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent_kvkk.required' => 'KVKK aday rızası zorunludur.',
            'consent_kvkk.accepted' => 'KVKK aday rızası kabul edilmelidir.',
        ];
    }
}
