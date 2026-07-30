<?php

namespace App\Enums;

enum DataSubjectRequestChannel: string
{
    case Portal = 'portal';
    case PublicForm = 'public_form';
    case Email = 'email';
    case Written = 'written';
    case Kep = 'kep';
}
