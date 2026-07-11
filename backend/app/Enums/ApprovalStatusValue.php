<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000006_create_document_extended_tables.php::approval_status */
enum ApprovalStatusValue: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
