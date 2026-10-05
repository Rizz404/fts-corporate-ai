<?php

namespace App\Modules\Support\Enums;

enum MessageVisibility: string
{
    case Public = 'public';
    case Internal = 'internal';
}
