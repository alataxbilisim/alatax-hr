<?php

namespace App\Enums;

enum SettingValueType: string
{
    case Bool = 'bool';
    case Int = 'int';
    case Decimal = 'decimal';
    case String = 'string';
    case Text = 'text';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Duration = 'duration';
    case Money = 'money';
    case Date = 'date';
    case Json = 'json';
}
