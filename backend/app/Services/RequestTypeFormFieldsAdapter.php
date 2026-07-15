<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * request_types.form_fields → FormEngine alan meta + form_data doğrulama.
 *
 * Sözleşme (application_forms.fields ile hizalı, yerinde adapter — migrate yok):
 * [
 *   {
 *     "id"|"name"|"key"|"field_key": string,
 *     "type": text|email|phone|textarea|select|checkbox|radio|file|date|number,
 *     "label": string,
 *     "required"?: bool,
 *     "options"?: string[] | {value,label}[],
 *     "placeholder"?: string
 *   },
 *   ...
 * ]
 */
class RequestTypeFormFieldsAdapter
{
    /**
     * @param  mixed  $formFields
     * @return list<array<string, mixed>>
     */
    public function normalizeFields(mixed $formFields): array
    {
        if (! is_array($formFields) || $formFields === []) {
            return [];
        }

        $normalized = [];
        foreach ($formFields as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $key = $this->resolveKey($raw, $index);
            if ($key === null) {
                continue;
            }

            $type = is_string($raw['type'] ?? null) ? strtolower((string) $raw['type']) : 'text';
            if (! in_array($type, $this->allowedTypes(), true)) {
                $type = 'text';
            }

            $options = $this->normalizeOptions($raw['options'] ?? null);

            $normalized[] = [
                'id' => -(1000 + $index),
                'company_id' => null,
                'entity_type' => 'employee_request',
                'is_system' => false,
                'system_key' => null,
                'field_key' => $key,
                'field_label' => is_string($raw['label'] ?? null) ? $raw['label'] : $key,
                'label_override' => null,
                'effective_label' => is_string($raw['label'] ?? null) ? $raw['label'] : $key,
                'field_type' => $type,
                'field_options' => $options,
                'is_required' => (bool) ($raw['required'] ?? false),
                'is_required_override' => null,
                'effective_required' => (bool) ($raw['required'] ?? false),
                'is_active' => true,
                'is_hidden' => false,
                'sort_order' => ($index + 1) * 10,
                'validation_rules' => null,
                'field_permission' => null,
                'placeholder' => is_string($raw['placeholder'] ?? null) ? $raw['placeholder'] : null,
                'help_text' => null,
                'default_value' => $raw['default_value'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  mixed  $formFields
     * @param  mixed  $formData
     *
     * @throws ValidationException
     */
    public function validateFormData(mixed $formFields, mixed $formData): array
    {
        $fields = $this->normalizeFields($formFields);
        if ($fields === []) {
            return is_array($formData) ? $formData : [];
        }

        $data = is_array($formData) ? $formData : [];
        $errors = [];
        $cleaned = [];

        foreach ($fields as $field) {
            $key = $field['field_key'];
            $value = $data[$key] ?? null;
            $required = (bool) $field['effective_required'];

            if ($required && $this->isEmpty($value)) {
                $errors[$key][] = "{$field['effective_label']} zorunludur.";

                continue;
            }

            if ($this->isEmpty($value)) {
                $cleaned[$key] = null;

                continue;
            }

            $type = $field['field_type'];
            if ($type === 'number' && ! is_numeric($value)) {
                $errors[$key][] = "{$field['effective_label']} sayısal olmalıdır.";

                continue;
            }
            if ($type === 'email' && is_string($value) && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$key][] = "{$field['effective_label']} geçerli bir e-posta olmalıdır.";

                continue;
            }
            if (in_array($type, ['select', 'radio'], true) && is_array($field['field_options']) && $field['field_options'] !== []) {
                $allowed = collect($field['field_options'])->pluck('value')->all();
                if (! in_array((string) $value, array_map('strval', $allowed), true)) {
                    $errors[$key][] = "{$field['effective_label']} geçersiz seçim.";

                    continue;
                }
            }

            $cleaned[$key] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $cleaned;
    }

    /**
     * @return list<string>
     */
    private function allowedTypes(): array
    {
        return ['text', 'email', 'phone', 'textarea', 'select', 'checkbox', 'radio', 'file', 'date', 'number'];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function resolveKey(array $raw, int $index): ?string
    {
        foreach (['field_key', 'key', 'name', 'id'] as $candidate) {
            if (isset($raw[$candidate]) && is_scalar($raw[$candidate]) && (string) $raw[$candidate] !== '') {
                return (string) $raw[$candidate];
            }
        }

        return 'field_'.$index;
    }

    /**
     * @return list<array{value: string, label: string}>|null
     */
    private function normalizeOptions(mixed $options): ?array
    {
        if (! is_array($options) || $options === []) {
            return null;
        }

        $out = [];
        foreach ($options as $opt) {
            if (is_string($opt) || is_numeric($opt)) {
                $out[] = ['value' => (string) $opt, 'label' => (string) $opt];

                continue;
            }
            if (is_array($opt) && isset($opt['value'])) {
                $out[] = [
                    'value' => (string) $opt['value'],
                    'label' => (string) ($opt['label'] ?? $opt['value']),
                ];
            }
        }

        return $out === [] ? null : $out;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }
}
