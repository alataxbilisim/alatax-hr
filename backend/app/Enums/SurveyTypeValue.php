<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000007_create_onboarding_extended_tables.php::survey_type */
enum SurveyTypeValue: string
{
    case Week1 = 'week_1';
    case Week4 = 'week_4';
    case Month3 = 'month_3';
    case Exit = 'exit';
}
