<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 0001_09_01_000003_create_asset_maintenance_table.php::status */
enum Status807611: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
