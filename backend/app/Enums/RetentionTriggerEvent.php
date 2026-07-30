<?php

namespace App\Enums;

enum RetentionTriggerEvent: string
{
    case IseGiris = 'ise_giris';
    case IstenAyrilma = 'isten_ayrilma';
    case BasvuruReddi = 'basvuru_reddi';
    case KayitTarihi = 'kayit_tarihi';
    case BelgeTarihi = 'belge_tarihi';
    case SonIslemTarihi = 'son_islem_tarihi';
}
