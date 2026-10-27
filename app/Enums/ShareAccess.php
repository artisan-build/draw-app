<?php

declare(strict_types=1);

namespace App\Enums;

enum ShareAccess: string
{
    case View = 'view';
    case Edit = 'edit';
}
