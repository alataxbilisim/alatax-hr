<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000006_create_document_extended_tables.php::status */
enum Statusbee7e4: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
