<?php

use App\Pdf\GambarKuitansi;

test('untuk pembayaran 200 karakter muat dalam tinggi maksimal dan tetap terbaca', function () {
    $teks = str_repeat('Konsumsi rapat koordinasi guru ', 7);
    $teks = substr($teks, 0, 200);
    $tinggiMaks = 8.0;

    $ukuran = GambarKuitansi::ukuranFontPas($teks, 120.0, $tinggiMaks);

    expect(strlen($teks))->toBe(200)
        ->and($ukuran)->toBeGreaterThanOrEqual(6.0)
        ->and($ukuran)->toBeLessThan(9.0)
        ->and(count(GambarKuitansi::barisTeks($teks, 120.0, $ukuran)) * GambarKuitansi::tinggiBaris($ukuran))
        ->toBeLessThanOrEqual($tinggiMaks);
});

test('teks pendek memakai ukuran maksimal', function () {
    expect(GambarKuitansi::ukuranFontPas('Listrik Januari 2026', 120.0, 12.0))->toBe(9.0);
});

test('teks yang tidak mungkin muat berhenti di ukuran minimal', function () {
    expect(GambarKuitansi::ukuranFontPas(str_repeat('W', 200), 120.0, 3.0))->toBe(6.0);
});

test('kata tanpa spasi dipotong agar tidak melewati lebar', function () {
    $baris = GambarKuitansi::barisTeks(str_repeat('W', 200), 120.0, 6.0);

    expect($baris)->toHaveCount(4)
        ->and(implode('', $baris))->toBe(str_repeat('W', 200));
});

test('karakter di luar windows-1252 diganti, tidak menggagalkan', function () {
    expect(GambarKuitansi::teksPdf("Café – “Rp” \u{1F600}"))->toStartWith("Caf\xE9 \x96 \x93Rp\x94 ")
        ->and(GambarKuitansi::teksPdf("\xFF rusak"))->toContain('rusak');
});
