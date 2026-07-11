<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000007_create_onboarding_extended_tables.php::status */
enum Status135bc0: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
