<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000003_create_holidays_table.php::workflow_status */
enum WorkflowStatusValue: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
