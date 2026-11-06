<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusManual: string
{
    case TidakDilaksanakan = 'tidak_dilaksanakan';
    case DiLuarAplikasi = 'di_luar_aplikasi';
}
