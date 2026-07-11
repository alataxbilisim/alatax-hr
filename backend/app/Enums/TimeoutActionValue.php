<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000001_create_approval_workflows_table.php::timeout_action */
enum TimeoutActionValue: string
{
    case Escalate = 'escalate';
    case AutoApprove = 'auto_approve';
    case AutoReject = 'auto_reject';
}
