<?php

namespace App\Services\Approval;

/**
 * Onaya tabi entity kaydı — DatasetRegistry / SettingsRegistry deseni.
 */
final class ApprovalEntityDefinition
{
    /**
     * @param  list<string>  $conditionFields  B3 condition-meta + whitelist alanları
     */
    public function __construct(
        public readonly string $entityType,
        public readonly string $modelClass,
        public readonly string $trigger,
        public readonly string $triggerPoint,
        public readonly string $onApproved,
        public readonly string $onRejected,
        public readonly array $conditionFields,
        public readonly string $cancelBehavior,
        public readonly string $labelTr,
    ) {}
}
