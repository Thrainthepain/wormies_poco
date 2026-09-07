<?php

declare(strict_types=1);

namespace App\Enums;

enum PocoSource: string
{
    case Esi = 'esi';
    case Manual = 'manual';
}
