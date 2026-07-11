<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000009_create_asset_extended_tables.php::license_type */
enum LicenseTypeValue: string
{
    case Perpetual = 'perpetual';
    case Subscription = 'subscription';
    case PerSeat = 'per_seat';
    case Concurrent = 'concurrent';
    case Site = 'site';
    case OpenSource = 'open_source';
}
