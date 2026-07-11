<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 0001_05_01_000002_create_onboarding_tasks_table.php::type */
enum TypeValue: string
{
    case DocumentUpload = 'document_upload';
    case DocumentFill = 'document_fill';
    case Training = 'training';
    case Meeting = 'meeting';
    case SystemSetup = 'system_setup';
    case Quiz = 'quiz';
    case Custom = 'custom';
}
