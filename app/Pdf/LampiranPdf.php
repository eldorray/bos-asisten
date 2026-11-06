<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Enums\TataLetakBerkas;

/**
 * Satu foto yang akan dicetak. $path relatif terhadap disk local.
 */
final readonly class LampiranPdf
{
    public function __construct(
        public string $path,
        public int $lebar,
        public int $tinggi,
        public TataLetakBerkas $tataLetak,
    ) {}
}
