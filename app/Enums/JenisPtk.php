<?php

declare(strict_types=1);

namespace App\Enums;

enum JenisPtk: int
{
    case Guru = 1;
    case Administrasi = 2;
    case Perpustakaan = 3;
    case Lainnya = 4;
}
