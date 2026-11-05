<?php

use App\Impor\ArkasParser;
use App\Impor\ErkamParser;
use App\Impor\ImporDitolak;
use App\Impor\PdfTokenReader;

dataset('pdf asli', [
    'Januari' => ['01_Kertas_Kerja_Januari_2026.pdf', 'arkas-januari-2026'],
    'Maret' => ['03_Kertas_Kerja_Maret_2026.pdf', 'arkas-maret-2026'],
]);

test('PDF asli terbaca sama dengan fixture', function (string $berkas, string $fixture) {
    $dir = getenv('SPJ_SAMPLE_DIR');

    if (! is_string($dir) || $dir === '') {
        $this->markTestSkipped('SPJ_SAMPLE_DIR tidak diset.');
    }

    $path = rtrim($dir, '/').'/'.$berkas;

    if (! is_file($path)) {
        $this->markTestSkipped("{$berkas} tidak ada di SPJ_SAMPLE_DIR.");
    }

    $parser = new ArkasParser;

    expect($parser->parse((new PdfTokenReader)->baca($path)))
        ->toEqual($parser->parse(tokenFixture($fixture)));
})->with('pdf asli');

test('PDF ERKAM asli terbaca sama dengan fixture', function () {
    $dir = getenv('SPJ_SAMPLE_DIR_ERKAM');

    if (! is_string($dir) || $dir === '') {
        $this->markTestSkipped('SPJ_SAMPLE_DIR_ERKAM tidak diset.');
    }

    $path = rtrim($dir, '/').'/erkam2026.pdf';

    if (! is_file($path)) {
        $this->markTestSkipped('erkam2026.pdf tidak ada di SPJ_SAMPLE_DIR_ERKAM.');
    }

    $parser = new ErkamParser;

    expect($parser->parse((new PdfTokenReader)->baca($path)))
        ->toEqual($parser->parse(tokenFixture('erkam-2026')));
});

test('file bukan PDF ditolak', function () {
    $path = tempnam(sys_get_temp_dir(), 'spj');
    file_put_contents($path, 'ini bukan pdf');

    try {
        (new PdfTokenReader)->baca($path);
    } finally {
        unlink($path);
    }
})->throws(ImporDitolak::class);
