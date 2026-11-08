<?php

declare(strict_types=1);

namespace App\Enums;

enum JenisVersiRkas: string
{
    case Awal = 'awal';
    case Pergeseran = 'pergeseran';
    case Perubahan = 'perubahan';
}
