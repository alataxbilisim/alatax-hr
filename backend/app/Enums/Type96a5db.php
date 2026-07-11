<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000010_create_survey_tables.php::type */
enum Type96a5db: string
{
    case Engagement = 'engagement';
    case Satisfaction = 'satisfaction';
    case Pulse = 'pulse';
    case Enps = 'enps';
    case Onboarding = 'onboarding';
    case Exit = 'exit';
    case Custom = 'custom';
}
