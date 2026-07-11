<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000009_create_asset_extended_tables.php::depreciation_method */
enum DepreciationMethodValue: string
{
    case None = 'none';
    case StraightLine = 'straight_line';
    case DecliningBalance = 'declining_balance';
}
