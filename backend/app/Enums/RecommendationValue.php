<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000005_create_recruitment_extended_tables.php::recommendation */
enum RecommendationValue: string
{
    case StrongHire = 'strong_hire';
    case Hire = 'hire';
    case NoDecision = 'no_decision';
    case NoHire = 'no_hire';
    case StrongNoHire = 'strong_no_hire';
}
