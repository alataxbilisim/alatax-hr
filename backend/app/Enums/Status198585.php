<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000005_create_recruitment_extended_tables.php::status */
enum Status198585: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';
}
