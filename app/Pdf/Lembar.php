<?php

declare(strict_types=1);

namespace App\Pdf;

final readonly class Lembar
{
    /**
     * @param  list<Penempatan>  $penempatan
     */
    public function __construct(
        public array $penempatan,
    ) {}
}
