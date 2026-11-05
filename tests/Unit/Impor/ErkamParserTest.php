<?php

use App\Enums\SistemAnggaran;
use App\Impor\ErkamParser;
use App\Impor\ImporDitolak;
use App\Impor\PdfAnggaranParser;
use App\Impor\Token;

/**
 * @param  list<Token>  $token
 * @param  callable(Token): bool  $cocok
 * @return list<Token>
 */
function ubahTokenErkam(array $token, callable $cocok, ?string $teks, int $urutan = 0): array
{
    $hasil = [];
    $ke = 0;

    foreach ($token as $t) {
        if ($cocok($t) && $ke++ === $urutan) {
            if ($teks === null) {
                continue;
            }

            $t = new Token($t->halaman, $t->x, $t->y, $teks);
        }

        $hasil[] = $t;
    }

    return $hasil;
}

/**
 * @param  list<Token>  $token
 */
function tolakErkam(array $token, string $pesan): void
{
    try {
        (new ErkamParser)->parse($token);
    } catch (ImporDitolak $e) {
        expect($e->getMessage())->toContain($pesan);

        return;
    }

    test()->fail('ImporDitolak tidak dilempar.');
}

test('membaca RKAM', function () {
    $a = (new ErkamParser)->parse(tokenFixture('erkam-2026'));
    $t1 = array_values(array_filter($a->baris, fn ($b) => $b->tahap === 1));
    $t2 = array_values(array_filter($a->baris, fn ($b) => $b->tahap === 2));

    expect($a->sistem)->toBe(SistemAnggaran::Erkam)
        ->and($a->identitas)->toBe('111236710070')->and($a->tahun)->toBe(2026)
        ->and($a->bulan)->toBeNull()->and($a->sumberDana)->toBe('APBN - BOS')
        ->and($t1)->toHaveCount(14)->and($t2)->toHaveCount(10)
        ->and($a->baris)->toHaveCount(24)
        ->and($a->totalDokumen)->toBe(320150000);
    expect(array_sum(array_map(fn ($b) => $b->jumlah, $a->baris)))->toBe(320150000);
    expect($t2[4]->uraian)->toBe('Retribusi Sampah dan Kebersihan, dan Retribusi lainnya | Retribusi Sampah'); // terpotong antarhalaman
    expect($t2[0]->koefisien)->toBe('6 bulan')->and($t2[0]->harga)->toBe(15960000)->and($t2[0]->jumlah)->toBe(95760000)->and($t2[0]->kode)->toBe('3.2.1')
        ->and($t2[0]->pajak)->toBe(0)->and($t2[0]->kodeProgram)->toBeNull()->and($t2[0]->uraian)->toBe('Gaji/Honor Rutin PTK/Operator');
    expect($t2[1]->uraian)->toBe('Biaya -Cetak dan Penggandaan Lainnya | Penggandaan Soal')->and($t2[1]->kode)->toBe('4.5.3');
    expect($t1[1]->uraian)->toBe('Biaya -Produk Komputer & Aksesoris Lainnya Lainnya | Perangkat Jaringan');
});

test('Sub Total tidak cocok ditolak dan menyebut tahapnya', function () {
    $token = ubahTokenErkam(
        tokenFixture('erkam-2026'),
        fn (Token $t) => $t->halaman === 4 && $t->teks === '2.100.000' && abs($t->x - 634.2) < 1,
        '2.200.000',
    );

    tolakErkam($token, 'Tahap 1');
    tolakErkam($token, 'Rp 160.075.000');
    tolakErkam($token, 'Rp 160.175.000');
});

test('blok selain APBN - BOS ditolak', function () {
    $token = ubahTokenErkam(tokenFixture('erkam-2026'), fn (Token $t) => $t->teks === 'APBN - BOS', 'BOS Daerah');

    tolakErkam($token, 'BOS Daerah');
});

test('angka jumlah bukan angka ditolak dan menyebut barisnya', function () {
    $token = ubahTokenErkam(
        tokenFixture('erkam-2026'),
        fn (Token $t) => $t->halaman === 1 && abs($t->y - 285.4) < 1 && abs($t->x - 513.5) < 1,
        '15.96O.000',
    );

    tolakErkam($token, 'baris 4');
});

test('baris kegiatan kurang dari lima angka ditolak', function () {
    $token = ubahTokenErkam(
        tokenFixture('erkam-2026'),
        fn (Token $t) => $t->halaman === 1 && abs($t->y - 285.4) < 1 && abs($t->x - 593.5) < 1,
        null,
    );

    tolakErkam($token, 'baris 4');
});

test('baris kegiatan tanpa koefisien ditolak', function () {
    $token = ubahTokenErkam(
        tokenFixture('erkam-2026'),
        fn (Token $t) => $t->halaman === 1 && abs($t->y - 285.4) < 1 && $t->teks === '6 bulan',
        null,
    );

    tolakErkam($token, 'baris 4');
});

test('baris kegiatan tanpa uraian ditolak', function () {
    $token = ubahTokenErkam(
        tokenFixture('erkam-2026'),
        fn (Token $t) => $t->halaman === 1 && abs($t->y - 273.9) < 1 && abs($t->x - 177.9) < 1,
        null,
    );

    tolakErkam($token, 'baris 4');
});

test('baris tak dikenal di dalam blok ditolak', function () {
    $token = tokenFixture('erkam-2026');
    $token[] = new Token(4, 37.2, 285.0, '99');

    tolakErkam($token, 'baris 99');
});

test('Sub Total yang hilang ditolak', function () {
    $token = array_values(array_filter(
        tokenFixture('erkam-2026'),
        fn (Token $t) => $t->teks !== 'Sub Total',
    ));

    tolakErkam($token, 'tidak terbaca');
});

test('PdfAnggaranParser memilih parser dari judul', function () {
    $erkam = (new PdfAnggaranParser)->parseToken(tokenFixture('erkam-2026'));
    $arkas = (new PdfAnggaranParser)->parseToken(tokenFixture('arkas-maret-2026'));

    expect($erkam->sistem)->toBe(SistemAnggaran::Erkam)->and($arkas->sistem)->toBe(SistemAnggaran::Arkas);
});

test('PdfAnggaranParser menolak PDF lain', function () {
    (new PdfAnggaranParser)->parseToken([new Token(1, 10.0, 500.0, 'LAPORAN KEUANGAN')]);
})->throws(ImporDitolak::class);

test('PdfAnggaranParser menolak berkas yang bukan PDF', function () {
    $path = tempnam(sys_get_temp_dir(), 'spj');
    file_put_contents($path, 'ini bukan pdf');

    try {
        (new PdfAnggaranParser)->parse($path);
    } finally {
        unlink($path);
    }
})->throws(ImporDitolak::class);
