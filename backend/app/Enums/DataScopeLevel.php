<?php

namespace App\Enums;

/**
 * Satır düzeyi veri kapsamı (Data Scope).
 * Genişlik: own < team < department < branch < company
 */
enum DataScopeLevel: string
{
    case Own = 'own';
    case Team = 'team';
    case Department = 'department';
    case Branch = 'branch';
    case Company = 'company';

    /**
     * Karşılaştırma için genişlik skoru (büyük = daha geniş).
     */
    public function width(): int
    {
        return match ($this) {
            self::Own => 1,
            self::Team => 2,
            self::Department => 3,
            self::Branch => 4,
            self::Company => 5,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
