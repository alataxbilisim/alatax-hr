<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Rules\TurkishNationalId;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Entity custom_fields jsonb değerlerini CustomFieldDefinition'a göre doğrular.
 * Yalnızca firma custom alanları (is_system=false); sistem alanları FormFieldCatalogService.
 */
class CustomFieldValidationService
{
    public function __construct(
        protected FormFieldCatalogService $catalog
    ) {}

    /**
     * FormData JSON string veya array → array|null.
     *
     * @return array<string, mixed>|null
     */
    public function parseInput(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($raw) ? $raw : null;
    }

    /**
     * @param  array<string, mixed>|null  $customFields
     *
     * @throws ValidationException
     */
    public function validate(string $entityType, ?array $customFields): void
    {
        $definitions = CustomFieldDefinition::query()
            ->forEntity($entityType)
            ->customOnly()
            ->active()
            ->where('is_hidden', false)
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

            if ($definition->effectiveRequired() && $this->isEmpty($value)) {
                $errors[$attribute][] = "{$definition->effectiveLabel()} zorunludur.";

                continue;
            }

            if ($this->isEmpty($value)) {
                continue;
            }

            $typeError = $this->validateType($definition, $value);
            if ($typeError !== null) {
                $errors[$attribute][] = $typeError;

                continue;
            }

            $ruleError = $this->validateRules($definition, $value, $attribute);
            if ($ruleError !== null) {
                $errors[$attribute][] = $ruleError;
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
        $label = $definition->effectiveLabel();

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

    /**
     * validation_rules jsonb → uygulama (tckn, min:, max:, regex:…).
     */
    private function validateRules(CustomFieldDefinition $definition, mixed $value, string $attribute): ?string
    {
        foreach ($this->catalog->laravelRulesFromDefinition($definition) as $rule) {
            if ($rule instanceof TurkishNationalId) {
                $message = null;
                $rule->validate($attribute, $value, function (string $msg) use (&$message) {
                    $message = $msg;
                });
                if ($message !== null) {
                    return $message;
                }

                continue;
            }

            if (! is_string($rule)) {
                continue;
            }

            if (str_starts_with($rule, 'min:')) {
                $min = (int) substr($rule, 4);
                if (is_string($value) && mb_strlen($value) < $min) {
                    return "{$definition->effectiveLabel()} en az {$min} karakter olmalıdır.";
                }
                if (is_numeric($value) && (float) $value < $min) {
                    return "{$definition->effectiveLabel()} en az {$min} olmalıdır.";
                }
            }

            if (str_starts_with($rule, 'max:')) {
                $max = (int) substr($rule, 4);
                if (is_string($value) && mb_strlen($value) > $max) {
                    return "{$definition->effectiveLabel()} en fazla {$max} karakter olmalıdır.";
                }
                if (is_numeric($value) && (float) $value > $max) {
                    return "{$definition->effectiveLabel()} en fazla {$max} olmalıdır.";
                }
            }

            if (str_starts_with($rule, 'regex:')) {
                $pattern = substr($rule, 6);
                if (! is_string($value) || @preg_match($pattern, $value) !== 1) {
                    return "{$definition->effectiveLabel()} formatı geçersiz.";
                }
            }
        }

        return null;
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
            if (is_string($option) && $option === $stringValue) {
                return true;
            }
        }

        return false;
    }
}
