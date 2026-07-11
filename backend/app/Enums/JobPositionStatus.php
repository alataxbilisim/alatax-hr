<?php

namespace App\Enums;

enum JobPositionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Closed = 'closed';
}
