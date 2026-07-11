<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000010_create_survey_tables.php::question_type */
enum QuestionTypeValue: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Rating = 'rating';
    case Nps = 'nps';
    case Text = 'text';
    case Scale = 'scale';
    case Matrix = 'matrix';
}
