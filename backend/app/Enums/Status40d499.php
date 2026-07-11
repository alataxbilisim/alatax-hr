<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000010_create_survey_tables.php::status */
enum Status40d499: string
{
    case Started = 'started';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
}
