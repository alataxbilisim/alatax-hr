<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000008_create_training_extended_tables.php::status */
enum Statusa08a81: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
}
