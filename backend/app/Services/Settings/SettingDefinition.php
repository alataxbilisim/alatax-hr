<?php

namespace App\Services\Settings;

use App\Enums\SettingScopeType;
use App\Enums\SettingTier;
use App\Enums\SettingValueType;

/**
 * Kod tarafı ayar tanımı (D1a ReportField benzeri DTO).
 */
final class SettingDefinition
{
    /**
     * @param  list<string>  $pageKeys
     * @param  list<SettingScopeType>  $scopeLevels
     * @param  list<array{value: string|int|bool, label: string}>|null  $options
     * @param  array{min?: float|int, max?: float|int, regex?: string, required?: bool}|null  $validation
     */
    public function __construct(
        public readonly string $key,
        public readonly string $moduleKey,
        public readonly array $pageKeys,
        public readonly string $labelKey,
        public readonly string $descriptionKey,
        public readonly SettingValueType $type,
        public readonly mixed $default,
        public readonly array $scopeLevels,
        public readonly string $permission,
        public readonly SettingTier $tier = SettingTier::Basic,
        public readonly ?string $affectsKey = null,
        public readonly ?array $options = null,
        public readonly ?array $validation = null,
        public readonly ?string $legalMinKey = null,
        public readonly ?string $legalMaxKey = null,
        public readonly ?string $legacyCompanyPath = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toCatalogArray(mixed $resolvedValue, string $resolvedFrom, bool $isDefault): array
    {
        return [
            'key' => $this->key,
            'module_key' => $this->moduleKey,
            'page_keys' => $this->pageKeys,
            'label_key' => $this->labelKey,
            'description_key' => $this->descriptionKey,
            'type' => $this->type->value,
            'default' => $this->default,
            'value' => $resolvedValue,
            'resolved_from' => $resolvedFrom,
            'is_default' => $isDefault,
            'scope_levels' => array_map(fn (SettingScopeType $s) => $s->value, $this->scopeLevels),
            'permission' => $this->permission,
            'tier' => $this->tier->value,
            'affects_key' => $this->affectsKey,
            'options' => $this->options,
            'validation' => $this->validation,
            'legal_min_key' => $this->legalMinKey,
            'legal_max_key' => $this->legalMaxKey,
        ];
    }
}
