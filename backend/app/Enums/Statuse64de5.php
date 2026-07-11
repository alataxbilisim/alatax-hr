<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000004_create_performance_extended_tables.php::status */
enum Statuse64de5: string
{
    case NotStarted = 'not_started';
    case OnTrack = 'on_track';
    case AtRisk = 'at_risk';
    case Behind = 'behind';
    case Completed = 'completed';
}
