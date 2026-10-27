<?php

declare(strict_types=1);

namespace App\Enums;

enum BoardShareKind: string
{
    case User = 'user';
    case Team = 'team';
    case Link = 'link';
}
