<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000007_create_onboarding_extended_tables.php::status */
enum Status03a092: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
