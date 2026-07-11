<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000004_create_performance_extended_tables.php::status */
enum Statusb787a7: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
