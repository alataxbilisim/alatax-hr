<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 0001_07_01_000000_create_performance_periods_table.php::status */
enum Statusbaaba1: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
}
