<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000002_create_accrual_policies_table.php::type */
enum Type0a6d26: string
{
    case Accrual = 'accrual';
    case Usage = 'usage';
    case Adjustment = 'adjustment';
    case Carryover = 'carryover';
    case Expiry = 'expiry';
    case Encashment = 'encashment';
    case InitialGrant = 'initial_grant';
}
