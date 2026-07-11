<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 0001_09_01_000002_create_asset_assignments_table.php::condition_at_assignment */
enum ConditionAtAssignmentValue: string
{
    case NewItem = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
}
