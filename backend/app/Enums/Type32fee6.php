<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000005_create_recruitment_extended_tables.php::type */
enum Type32fee6: string
{
    case JobBoard = 'job_board';
    case Social = 'social';
    case Referral = 'referral';
    case CareerSite = 'career_site';
    case Agency = 'agency';
    case Other = 'other';
}
