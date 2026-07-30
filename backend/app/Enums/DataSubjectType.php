<?php

namespace App\Enums;

enum DataSubjectType: string
{
    case Employee = 'employee';
    case Candidate = 'candidate';
    case FormerEmployee = 'former_employee';
    case Visitor = 'visitor';
    case Other = 'other';
}
