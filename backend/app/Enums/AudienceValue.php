<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000010_create_survey_tables.php::audience */
enum AudienceValue: string
{
    case All = 'all';
    case Department = 'department';
    case Position = 'position';
    case Custom = 'custom';
}
