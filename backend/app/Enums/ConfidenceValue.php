<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000004_create_performance_extended_tables.php::confidence */
enum ConfidenceValue: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
