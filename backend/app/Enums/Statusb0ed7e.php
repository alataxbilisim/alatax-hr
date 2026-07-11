<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 0001_05_01_000002_create_onboarding_tasks_table.php::status */
enum Statusb0ed7e: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
