<?php

namespace App\Enums;

/** Auto (Faz 1). Migration: 0001_08_01_000002_create_training_participants_table.php::status */
enum Status7522a9: string
{
    case Registered = 'registered';
    case Attended = 'attended';
    case Absent = 'absent';
    case Excused = 'excused';
}
