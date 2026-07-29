<?php

namespace App\Enums;

/**
 * Aydınlatma ≠ açık rıza — ayrı kayıt tipleri (KVKK geçersizlik riski).
 */
enum KvkkConsentType: string
{
    case NoticeRead = 'aydinlatma_okundu';
    case ExplicitSpecial = 'acik_riza_ozel_nitelikli';
    case ExplicitAbroad = 'acik_riza_yurtdisi';
    case MarketingElectronic = 'ticari_elektronik_ileti';
    case Other = 'diger';

    public function isExplicitConsent(): bool
    {
        return in_array($this, [
            self::ExplicitSpecial,
            self::ExplicitAbroad,
            self::MarketingElectronic,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
