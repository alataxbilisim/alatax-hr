<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Entity custom_fields jsonb değerlerini CustomFieldDefinition'a göre doğrular.
 * Şimdilik yalnızca employee (employees.custom_fields kolonu).
 */
class CustomFieldValidationService
{
    /**
     * @param  array<string, mixed>|null  $customFields
     *
     * @throws ValidationException
     */
    public function validate(string $entityType, ?array $customFields): void
    {
        $definitions = CustomFieldDefinition::query()
            ->forEntity($entityType)
            ->active()
            ->ordered()
            ->get();

        if ($definitions->isEmpty()) {
            return;
        }

        $values = $customFields ?? [];
        $errors = [];

        foreach ($definitions as $definition) {
            $key = $definition->field_key;
            $attribute = "custom_fields.{$key}";
            $value = array_key_exists($key, $values) ? $values[$key] : null;

            if ($definition->is_required && $this->isEmpty($value)) {
                $errors[$attribute][] = "{$definition->field_label} zorunludur.";

                continue;
            }

            if ($this->isEmpty($value)) {
                continue;
            }

            $typeError = $this->validateType($definition, $value);
            if ($typeError !== null) {
                $errors[$attribute][] = $typeError;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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

    private function validateType(CustomFieldDefinition $definition, mixed $value): ?string
    {
        $label = $definition->field_label;

        return match ($definition->field_type) {
            CustomFieldDefinition::TYPE_NUMBER => is_numeric($value)
                ? null
                : "{$label} sayısal olmalıdır.",
            CustomFieldDefinition::TYPE_EMAIL => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : "{$label} geçerli bir e-posta olmalıdır.",
            CustomFieldDefinition::TYPE_URL => is_string($value) && filter_var($value, FILTER_VALIDATE_URL)
                ? null
                : "{$label} geçerli bir URL olmalıdır.",
            CustomFieldDefinition::TYPE_DATE => $this->isValidDateString($value)
                ? null
                : "{$label} geçerli bir tarih olmalıdır.",
            CustomFieldDefinition::TYPE_DATETIME => $this->isValidDateTimeString($value)
                ? null
                : "{$label} geçerli bir tarih-saat olmalıdır.",
            CustomFieldDefinition::TYPE_CHECKBOX => $this->isValidCheckbox($value)
                ? null
                : "{$label} geçerli bir onay değeri olmalıdır.",
            CustomFieldDefinition::TYPE_SELECT, CustomFieldDefinition::TYPE_RADIO => $this->isAllowedOption($definition, $value)
                ? null
                : "{$label} için geçersiz seçenek.",
            default => is_scalar($value) || $value === null
                ? null
                : "{$label} geçersiz değer.",
        };
    }

    private function isValidDateString(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $date = Carbon::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function isValidDateTimeString(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isValidCheckbox(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_int($value) && in_array($value, [0, 1], true)) {
            return true;
        }

        if (is_string($value) && in_array(strtolower($value), ['0', '1', 'true', 'false'], true)) {
            return true;
        }

        return false;
    }

    private function isAllowedOption(CustomFieldDefinition $definition, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $stringValue = (string) $value;
        $options = $definition->field_options ?? [];

        foreach ($options as $option) {
            if (is_array($option) && array_key_exists('value', $option) && (string) $option['value'] === $stringValue) {
                return true;
            }
            // Eski/bozuk string[] kayıtlarına tolerans
            if (is_string($option) && $option === $stringValue) {
                return true;
            }
        }

        return false;
    }
}
