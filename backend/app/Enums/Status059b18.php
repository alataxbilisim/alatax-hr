<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 0001_07_01_000002_create_performance_reviews_table.php::status */
enum Status059b18: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
