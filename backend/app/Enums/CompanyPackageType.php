<?php

namespace App\Enums;

enum CompanyPackageType: string
{
    case Starter = 'starter';
    case Professional = 'professional';
    case Enterprise = 'enterprise';
}
