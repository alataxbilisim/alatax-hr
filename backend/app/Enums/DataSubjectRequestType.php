<?php

namespace App\Enums;

enum DataSubjectRequestType: string
{
    case BilgiTalebi = 'bilgi_talebi';
    case IslenipIslenmedigi = 'islenip_islenmedigi';
    case AmacOgrenme = 'amac_ogrenme';
    case UcuncuKisiler = 'ucuncu_kisiler';
    case Duzeltme = 'duzeltme';
    case Silme = 'silme';
    case Itiraz = 'itiraz';
    case Tasinabilirlik = 'tasinabilirlik';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
