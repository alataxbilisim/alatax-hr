<?php

namespace App\Enums;

enum SettingScopeType: string
{
    case System = 'system';
    case Company = 'company';
    case Branch = 'branch';
    case Department = 'department';
    case User = 'user';

    /**
     * Çözümleme sırası: kullanıcı → departman → şube → firma → sistem.
     *
     * @return list<self>
     */
    public static function resolutionOrder(): array
    {
        return [
            self::User,
            self::Department,
            self::Branch,
            self::Company,
            self::System,
        ];
    }
}
