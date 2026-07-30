<?php

namespace App\Enums;

enum RetentionDecisionType: string
{
    case Destroy = 'destroy';
    case Defer = 'defer';
    case Exclude = 'exclude';
}
