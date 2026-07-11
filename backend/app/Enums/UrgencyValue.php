<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000009_create_asset_extended_tables.php::urgency */
enum UrgencyValue: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
