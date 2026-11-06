<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

class Terbilang
{
    private const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    private const SKALA = ['', 'ribu', 'juta', 'miliar', 'triliun', 'kuadriliun', 'kuintiliun'];

    public static function rupiah(int $nilai): string
    {
        if ($nilai < 0) {
            throw new InvalidArgumentException('Terbilang tidak mendukung nilai negatif.');
        }

        if ($nilai === 0) {
            return 'Nol rupiah';
        }

        $bagian = [];

        foreach (array_keys(self::SKALA) as $tingkat) {
            $kelompok = intdiv($nilai, 1000 ** $tingkat) % 1000;

            if ($kelompok === 0) {
                continue;
            }

            array_unshift($bagian, $tingkat === 1 && $kelompok === 1
                ? 'seribu'
                : trim(self::ratusan($kelompok).' '.self::SKALA[$tingkat]));
        }

        return ucfirst(implode(' ', $bagian)).' rupiah';
    }

    private static function ratusan(int $n): string
    {
        $kata = [];

        if ($n >= 100) {
            $ratus = intdiv($n, 100);
            $kata[] = $ratus === 1 ? 'seratus' : self::SATUAN[$ratus].' ratus';
            $n %= 100;
        }

        if ($n >= 20) {
            $kata[] = self::SATUAN[intdiv($n, 10)].' puluh';
            $n %= 10;
        } elseif ($n >= 12) {
            $kata[] = self::SATUAN[$n - 10].' belas';
            $n = 0;
        }

        if ($n > 0) {
            $kata[] = self::SATUAN[$n];
        }

        return implode(' ', $kata);
    }
}
