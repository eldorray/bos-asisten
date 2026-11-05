<?php

declare(strict_types=1);

namespace App\Impor;

/**
 * Bantuan bersama untuk parser PDF anggaran: angka ketat, kesamaan baris, dan pengelompokan baris.
 */
final class BacaToken
{
    public const TOLERANSI_BARIS = 1.5;

    public static function sebaris(Token $a, Token $b): bool
    {
        return $a->halaman === $b->halaman && abs($a->y - $b->y) <= self::TOLERANSI_BARIS;
    }

    public static function angka(string $teks, string $konteks): int
    {
        if (preg_match('/^\d{1,3}(\.\d{3})*$|^\d+$/', $teks) !== 1) {
            throw new ImporDitolak("{$konteks} tidak terbaca: \"{$teks}\".");
        }

        return (int) str_replace('.', '', $teks);
    }

    public static function rupiah(int $nilai): string
    {
        return 'Rp '.number_format($nilai, 0, ',', '.');
    }

    /**
     * Mengelompokkan token menjadi baris, urut halaman lalu dari atas ke bawah; token dalam baris urut dari kiri.
     *
     * @param  list<Token>  $token
     * @return list<list<Token>>
     */
    public static function barisBaris(array $token): array
    {
        usort($token, fn (Token $a, Token $b) => $a->halaman <=> $b->halaman ?: $b->y <=> $a->y);

        $hasil = [];
        $sekarang = [];

        foreach ($token as $t) {
            if ($sekarang !== [] && ! self::sebaris($t, $sekarang[0])) {
                $hasil[] = $sekarang;
                $sekarang = [];
            }

            $sekarang[] = $t;
        }

        if ($sekarang !== []) {
            $hasil[] = $sekarang;
        }

        return array_map(function (array $baris) {
            usort($baris, fn (Token $a, Token $b) => $a->x <=> $b->x);

            return $baris;
        }, $hasil);
    }
}
