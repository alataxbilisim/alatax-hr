<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000009_create_asset_extended_tables.php::status */
enum Status7fdf25: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
}
