<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000010_create_survey_tables.php::recurrence */
enum RecurrenceValue: string
{
    case None = 'none';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
}
