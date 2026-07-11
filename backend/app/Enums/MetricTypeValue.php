<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000004_create_performance_extended_tables.php::metric_type */
enum MetricTypeValue: string
{
    case Number = 'number';
    case Percentage = 'percentage';
    case Currency = 'currency';
    case Boolean = 'boolean';
    case Milestone = 'milestone';
}
