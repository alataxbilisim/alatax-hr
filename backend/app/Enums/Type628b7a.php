<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 0001_09_01_000003_create_asset_maintenance_table.php::type */
enum Type628b7a: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Upgrade = 'upgrade';
}
