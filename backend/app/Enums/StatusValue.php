<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 0001_05_01_000001_create_onboarding_processes_table.php::status */
enum StatusValue: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
