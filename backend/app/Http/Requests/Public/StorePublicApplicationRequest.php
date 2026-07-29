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

        // Eski tek kutu: her iki zorunlu onayı doldur (yeni UI ayrı tutar)
        if ($this->boolean('consent_kvkk')
            && ! $this->has('consent_notice_read')
            && ! $this->has('consent_explicit_special')) {
            $this->merge([
                'consent_notice_read' => '1',
                'consent_explicit_special' => '1',
            ]);
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
            // D2a: aydınlatma ≠ açık rıza — ayrı onaylar (tek checkbox yasak)
            'consent_notice_read' => ['required', 'accepted'],
            'consent_explicit_special' => ['required', 'accepted'],
            'privacy_notice_id' => ['nullable', 'integer'],
            // Geriye uyumluluk: eski istemciler consent_kvkk gönderirse kabul (her iki kutu ile)
            'consent_kvkk' => ['sometimes', 'accepted'],
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
            'consent_notice_read.required' => 'Aydınlatma metninin okunduğu onaylanmalıdır.',
            'consent_notice_read.accepted' => 'Aydınlatma metninin okunduğu onaylanmalıdır.',
            'consent_explicit_special.required' => 'Açık rıza onayı zorunludur.',
            'consent_explicit_special.accepted' => 'Açık rıza onayı zorunludur.',
            'consent_kvkk.accepted' => 'KVKK aday rızası kabul edilmelidir.',
        ];
    }
}
