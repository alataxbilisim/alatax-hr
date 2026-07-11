<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000002_create_accrual_policies_table.php::accrual_type */
enum AccrualTypeValue: string
{
    case Annual = 'annual';
    case Monthly = 'monthly';
    case PerPayPeriod = 'per_pay_period';
    case Hourly = 'hourly';
    case Custom = 'custom';
}
