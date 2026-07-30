<?php

namespace App\Enums;

enum RetentionStrategy: string
{
    case Anonymize = 'anonymize';
    case Pseudonymize = 'pseudonymize';
    case HardDelete = 'hard_delete';
    case Archive = 'archive';

    /** Collector destroy() parametresi */
    public function toCollectorStrategy(): string
    {
        return $this === self::HardDelete ? 'delete' : 'anonymize';
    }
}
