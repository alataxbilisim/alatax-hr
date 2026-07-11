<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 2024_12_24_000001_create_approval_workflows_table.php::approver_type */
enum ApproverTypeValue: string
{
    case DirectManager = 'direct_manager';
    case DepartmentHead = 'department_head';
    case SpecificUser = 'specific_user';
    case SpecificRole = 'specific_role';
    case Hr = 'hr';
    case Cfo = 'cfo';
    case Ceo = 'ceo';
}
