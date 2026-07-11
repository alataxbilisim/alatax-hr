<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000006_create_document_extended_tables.php::status */
enum Statusb5550c: string
{
    case Missing = 'missing';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
