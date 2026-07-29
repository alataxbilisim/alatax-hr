<?php

namespace App\Services\Reports;

/**
 * Tek bir rapor alanı — kolon whitelist kaydı.
 */
final class ReportField
{
    public const SENSITIVITY_NORMAL = 'normal';

    public const SENSITIVITY_PERSONAL = 'personal';

    public const SENSITIVITY_SPECIAL = 'special';

    public const SENSITIVITY_ANONYMOUS = 'anonymous_source';

    public function __construct(
        public readonly string $key,
        public readonly string $column,
        public readonly string $type,
        public readonly string $label,
        public readonly string $labelKey,
        public readonly string $role,
        public readonly ?string $permission = null,
        public readonly bool $sensitive = false,
        public readonly bool $isCustom = false,
        public readonly ?string $customJsonKey = null,
        public readonly ?string $customJsonColumn = null,
        public readonly string $sensitivity = self::SENSITIVITY_NORMAL,
    ) {}

    public function isMeasure(): bool
    {
        return $this->role === 'measure';
    }

    public function isDimension(): bool
    {
        return $this->role === 'dimension';
    }

    public function requiresMinCellGuard(): bool
    {
        return in_array($this->sensitivity, [self::SENSITIVITY_SPECIAL, self::SENSITIVITY_ANONYMOUS], true);
    }

    public function isClassifiedSensitive(): bool
    {
        return $this->sensitive
            || in_array($this->sensitivity, [
                self::SENSITIVITY_PERSONAL,
                self::SENSITIVITY_SPECIAL,
                self::SENSITIVITY_ANONYMOUS,
            ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'label_key' => $this->labelKey,
            'role' => $this->role,
            'permission' => $this->permission,
            'sensitive' => $this->sensitive || $this->isClassifiedSensitive(),
            'sensitivity' => $this->sensitivity,
            'is_custom' => $this->isCustom,
        ];
    }
}
