<?php

namespace App\Enums;

enum UserType: string
{
    case SuperAdmin = 'super_admin';
    case CompanyAdmin = 'company_admin';
    case User = 'user';
}
