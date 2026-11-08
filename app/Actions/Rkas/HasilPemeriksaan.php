<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

final readonly class HasilPemeriksaan
{
    /**
     * @param  list<string>  $pemblokir
     * @param  list<string>  $peringatan
     */
    public function __construct(
        public array $pemblokir,
        public array $peringatan,
    ) {}

    public function lolos(): bool
    {
        return $this->pemblokir === [];
    }

    /**
     * @return array{pemblokir: list<string>, peringatan: list<string>}
     */
    public function props(): array
    {
        return ['pemblokir' => $this->pemblokir, 'peringatan' => $this->peringatan];
    }
}
