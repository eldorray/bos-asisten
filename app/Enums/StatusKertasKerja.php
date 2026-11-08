<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusKertasKerja: string
{
    case Draf = 'draf';
    case Siap = 'siap';
    case Disahkan = 'disahkan';
}
