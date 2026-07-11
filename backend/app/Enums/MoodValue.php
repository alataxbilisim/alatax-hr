<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000004_create_performance_extended_tables.php::mood */
enum MoodValue: string
{
    case VeryNegative = 'very_negative';
    case Negative = 'negative';
    case Neutral = 'neutral';
    case Positive = 'positive';
    case VeryPositive = 'very_positive';
}
