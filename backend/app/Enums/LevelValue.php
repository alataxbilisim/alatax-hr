<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000004_create_performance_extended_tables.php::level */
enum LevelValue: string
{
    case Company = 'company';
    case Department = 'department';
    case Team = 'team';
    case Individual = 'individual';
}
