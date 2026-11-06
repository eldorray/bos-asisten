<?php

declare(strict_types=1);

namespace App\Impor;

final readonly class Token
{
    public function __construct(
        public int $halaman,
        public float $x,
        public float $y,
        public string $teks,
    ) {}
}
