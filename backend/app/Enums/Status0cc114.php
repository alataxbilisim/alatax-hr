<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000001_create_approval_workflows_table.php::status */
enum Status0cc114: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case Escalated = 'escalated';
}
