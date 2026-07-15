<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public: kariyer başvurusu
    }

    protected function prepareForValidation(): void
    {
        $routeSlug = $this->route('companySlug');
        if (is_string($routeSlug) && $routeSlug !== '' && ! $this->filled('company_slug')) {
            $this->merge(['company_slug' => $routeSlug]);
        }

        $formData = $this->input('form_data');
        if (is_string($formData) && $formData !== '') {
            $decoded = json_decode($formData, true);
            if (is_array($decoded)) {
                $this->merge(['form_data' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_slug' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
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
            'company_slug.required' => 'Firma kimliği (slug) zorunludur.',
            'consent_kvkk.required' => 'KVKK aday rızası zorunludur.',
            'consent_kvkk.accepted' => 'KVKK aday rızası kabul edilmelidir.',
        ];
    }
}
