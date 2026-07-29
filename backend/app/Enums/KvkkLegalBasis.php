<?php

namespace App\Enums;

enum KvkkLegalBasis: string
{
    case ExplicitConsent = 'explicit_consent';
    case Contract = 'contract';
    case LegalObligation = 'legal_obligation';
    case LegitimateInterest = 'legitimate_interest';
    case LegalProvision = 'legal_provision';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
