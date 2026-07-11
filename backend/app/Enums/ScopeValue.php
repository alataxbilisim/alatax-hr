<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000006_create_document_extended_tables.php::scope */
enum ScopeValue: string
{
    case All = 'all';
    case Department = 'department';
    case Position = 'position';
    case EmployeeType = 'employee_type';
}
