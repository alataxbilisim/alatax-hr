<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000005_create_recruitment_extended_tables.php::status */
enum Status76e474: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Rescheduled = 'rescheduled';
}
