<?php

namespace App\Enums;

enum SettingTier: string
{
    case Basic = 'basic';
    case Advanced = 'advanced';
    case System = 'system';
}
