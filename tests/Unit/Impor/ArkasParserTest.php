<?php

use App\Enums\SistemAnggaran;
use App\Impor\ArkasParser;
use App\Impor\ImporDitolak;
use App\Impor\Token;

test('membaca Maret', function () {
    $a = (new ArkasParser)->parse(tokenFixture('arkas-maret-2026'));

    expect($a->sistem)->toBe(SistemAnggaran::Arkas)
        ->and($a->identitas)->toBe('20606714')
        ->and($a->bulan)->toBe(3)->and($a->tahun)->toBe(2026)
        ->and($a->sumberDana)->toBe('BOSP Reguler')
        ->and($a->baris)->toHaveCount(10)
        ->and($a->totalDokumen)->toBe(19224000);
    expect(array_sum(array_map(fn ($b) => $b->jumlah, $a->baris)))->toBe(19224000);
    expect($a->baris[0]->uraian)->toBe('Gaji Petugas Administrasi Non PNS D.III-Tipe : Gaji Petugas Administrasi; Deskripsi : Gaji Tenaga Kependidikan Non PNS SMP Negeri');
    expect($a->baris[0]->kode)->toBe('5.1.02.02.01.0013')
        ->and($a->baris[0]->kodeProgram)->toBe('04.06.03')
        ->and($a->baris[0]->koefisien)->toBe('25 hari')
        ->and($a->baris[0]->harga)->toBe(50000)
        ->and($a->baris[0]->pajak)->toBe(0)
        ->and($a->baris[0]->tahap)->toBeNull();
    expect($a->baris[4]->uraian)->toBe('Bola Volley-Bola Volley');
    expect($a->baris[9]->harga)->toBe(12500);
});

test('membaca Januari', function () {
    $a = (new ArkasParser)->parse(tokenFixture('arkas-januari-2026'));

    expect($a->bulan)->toBe(1)->and($a->tahun)->toBe(2026)
        ->and($a->baris)->toHaveCount(6)
        ->and($a->totalDokumen)->toBe(15100000);
    expect(array_sum(array_map(fn ($b) => $b->jumlah, $a->baris)))->toBe(15100000);
});

test('dokumen tanpa baris Jumlah ditolak', function () {
    $token = array_values(array_filter(
        tokenFixture('arkas-maret-2026'),
        fn (Token $t) => ! ($t->teks === 'Jumlah' && $t->x < 600),
    ));

    (new ArkasParser)->parse($token);
})->throws(ImporDitolak::class);

test('baris yang hilang membuat total tidak cocok dan ditolak', function () {
    // buang seluruh baris nomor 25 (Rp 250.000) dari halaman 2
    $token = array_values(array_filter(
        tokenFixture('arkas-maret-2026'),
        fn (Token $t) => ! ($t->halaman === 2 && abs($t->y - 204) <= 1.5),
    ));

    try {
        (new ArkasParser)->parse($token);
        $this->fail('Harus ditolak');
    } catch (ImporDitolak $e) {
        expect($e->getMessage())->toContain('dokumen Rp 19.224.000')->toContain('terbaca Rp 18.974.000');
    }
});

test('selisih total menyebut angka dokumen dan angka terbaca', function () {
    $token = array_map(
        fn (Token $t) => $t->teks === '19.224.000' ? new Token($t->halaman, $t->x, $t->y, '19.224.001') : $t,
        tokenFixture('arkas-maret-2026'),
    );

    try {
        (new ArkasParser)->parse($token);
        $this->fail('Harus ditolak');
    } catch (ImporDitolak $e) {
        expect($e->getMessage())->toContain('Rp 19.224.001')->toContain('Rp 19.224.000');
    }
});

test('baris berkode tanpa jumlah terbaca ditolak dengan nomor baris', function () {
    $token = tokenFixture('arkas-maret-2026');
    $hilang = array_values(array_filter($token, fn (Token $t) => $t->teks === '250.000' && $t->x > 780))[0];
    $token = array_values(array_filter($token, fn (Token $t) => $t !== $hilang));

    (new ArkasParser)->parse($token);
})->throws(ImporDitolak::class, 'baris 25');

test('jumlah bukan angka ditolak', function () {
    $token = array_map(
        fn (Token $t) => $t->teks === '250.000' && $t->x > 780 ? new Token($t->halaman, $t->x, $t->y, 'abc') : $t,
        tokenFixture('arkas-maret-2026'),
    );

    (new ArkasParser)->parse($token);
})->throws(ImporDitolak::class, 'baris 25');

test('nilai pada baris Jumlah yang bukan angka ditolak', function () {
    $token = array_map(
        fn (Token $t) => $t->teks === '19.224.000' ? new Token($t->halaman, $t->x, $t->y, '-') : $t,
        tokenFixture('arkas-maret-2026'),
    );

    (new ArkasParser)->parse($token);
})->throws(ImporDitolak::class);
