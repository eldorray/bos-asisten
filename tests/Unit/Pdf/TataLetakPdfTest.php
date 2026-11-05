<?php

use App\Enums\TataLetakBerkas;
use App\Pdf\BlokPdf;
use App\Pdf\LampiranPdf;
use App\Pdf\Lembar;
use App\Pdf\Penempatan;
use App\Pdf\TataLetakPdf;

function lampiranPenuh(string $path, int $lebar = 1200, int $tinggi = 1600): LampiranPdf
{
    return new LampiranPdf($path, $lebar, $tinggi, TataLetakBerkas::Penuh);
}

function lampiranKisi(string $path, int $lebar = 1600, int $tinggi = 1200): LampiranPdf
{
    return new LampiranPdf($path, $lebar, $tinggi, TataLetakBerkas::Kisi);
}

/**
 * @param  list<Lembar>  $lembar
 * @return list<list<string>>
 */
function isiTiapLembar(array $lembar): array
{
    return array_map(
        fn (Lembar $l): array => array_map(
            fn (Penempatan $p): string => $p->jenis === 'kuitansi' ? 'kuitansi' : $p->isi->path,
            $l->penempatan,
        ),
        $lembar,
    );
}

function fotoDiDalamMargin(Penempatan $p): void
{
    expect($p->x)->toBeGreaterThanOrEqual(8.0)
        ->and($p->y)->toBeGreaterThanOrEqual(8.0)
        ->and($p->x + $p->lebar)->toBeLessThanOrEqual(202.0 + 0.001)
        ->and($p->y + $p->tinggi)->toBeLessThanOrEqual(289.0 + 0.001);
}

test('kuitansi tanpa lampiran berbagi lembar paling banyak tiga', function () {
    $blok = fn (int $n): array => array_fill(0, $n, new BlokPdf([null], []));

    expect(TataLetakPdf::susun($blok(3)))->toHaveCount(1)
        ->and(TataLetakPdf::susun($blok(4)))->toHaveCount(2);

    $tiga = TataLetakPdf::susun($blok(3))[0]->penempatan;

    expect(array_map(fn (Penempatan $p): array => [$p->x, $p->y, $p->lebar, $p->tinggi], $tiga))->toBe([
        [0.0, 0.0, 210.0, 99.0],
        [0.0, 99.0, 210.0, 99.0],
        [0.0, 198.0, 210.0, 99.0],
    ]);
});

test('listrik: kuitansi lalu struk dalam satu lembar, struk mengisi sisa lembar', function () {
    $lembar = TataLetakPdf::susun([new BlokPdf([null], [lampiranPenuh('struk.jpg', 1000, 1000)])]);

    expect(isiTiapLembar($lembar))->toBe([['kuitansi', 'struk.jpg']]);

    $foto = $lembar[0]->penempatan[1];

    fotoDiDalamMargin($foto);
    expect($foto->y)->toBeGreaterThanOrEqual(99.0)
        ->and($foto->tinggi)->toEqualWithDelta(182.0, 0.01);
});

test('rapat: tiga lembar sesuai contoh', function () {
    $lembar = TataLetakPdf::susun([new BlokPdf([null], [
        lampiranPenuh('undangan.jpg'),
        lampiranPenuh('hadir.jpg'),
        lampiranPenuh('notulen.jpg'),
        lampiranKisi('foto1.jpg'),
        lampiranKisi('foto2.jpg'),
    ])]);

    expect(isiTiapLembar($lembar))->toBe([
        ['kuitansi', 'undangan.jpg'],
        ['hadir.jpg', 'notulen.jpg'],
        ['foto1.jpg', 'foto2.jpg'],
    ]);

    [$hadir, $notulen] = $lembar[1]->penempatan;
    expect($hadir->y + $hadir->tinggi)->toBeLessThanOrEqual($notulen->y);

    [$foto1, $foto2] = $lembar[2]->penempatan;
    expect($foto1->x + $foto1->lebar)->toBeLessThanOrEqual($foto2->x)
        ->and($foto1->y)->toBe($foto2->y);
});

test('acara dua kuitansi: kuitansi ditumpuk, foto mengisi sisa, tidak ada yang tercetak dua kali', function () {
    $lembar = TataLetakPdf::susun([new BlokPdf([null, null], [lampiranPenuh('undangan.jpg')])]);

    expect(isiTiapLembar($lembar))->toBe([['kuitansi', 'kuitansi', 'undangan.jpg']]);

    $foto = $lembar[0]->penempatan[2];

    fotoDiDalamMargin($foto);
    expect($foto->y)->toBeGreaterThanOrEqual(198.0);
});

test('tiga kuitansi menghabiskan lembar, lampiran pindah ke lembar berikutnya', function () {
    $lembar = TataLetakPdf::susun([new BlokPdf([null, null, null], [lampiranPenuh('a.jpg'), lampiranPenuh('b.jpg')])]);

    expect(isiTiapLembar($lembar))->toBe([
        ['kuitansi', 'kuitansi', 'kuitansi'],
        ['a.jpg', 'b.jpg'],
    ]);
});

test('empat kuitansi: lampiran pertama mengisi sisa lembar kedua', function () {
    $lembar = TataLetakPdf::susun([new BlokPdf([null, null, null, null], [lampiranPenuh('a.jpg')])]);

    expect(isiTiapLembar($lembar))->toBe([
        ['kuitansi', 'kuitansi', 'kuitansi'],
        ['kuitansi', 'a.jpg'],
    ]);
});

test('kisi dua kolom dua baris per lembar', function () {
    $kisi = array_map(fn (int $i): LampiranPdf => lampiranKisi("f{$i}.jpg"), range(1, 6));
    $lembar = TataLetakPdf::susun([new BlokPdf([null], [lampiranPenuh('undangan.jpg'), ...$kisi])]);

    expect(isiTiapLembar($lembar))->toBe([
        ['kuitansi', 'undangan.jpg'],
        ['f1.jpg', 'f2.jpg', 'f3.jpg', 'f4.jpg'],
        ['f5.jpg', 'f6.jpg'],
    ]);

    foreach ($lembar[1]->penempatan as $foto) {
        fotoDiDalamMargin($foto);
    }

    [$f1, $f2, $f3] = $lembar[1]->penempatan;
    expect($f1->y)->toBe($f2->y)
        ->and($f1->x)->toBe($f3->x)
        ->and($f1->y + $f1->tinggi)->toBeLessThanOrEqual($f3->y);
});

test('lampiran pertama berupa kisi memulai kisi dua kolom di sisa lembar, bukan satu foto penuh', function () {
    $lembar = TataLetakPdf::susun([new BlokPdf([null], [
        lampiranKisi('f1.jpg'),
        lampiranKisi('f2.jpg'),
        lampiranKisi('f3.jpg'),
    ])]);

    expect(isiTiapLembar($lembar))->toBe([
        ['kuitansi', 'f1.jpg', 'f2.jpg'],
        ['f3.jpg'],
    ]);

    [, $f1, $f2] = $lembar[0]->penempatan;
    $selKisi = TataLetakPdf::susun([new BlokPdf([null, null, null], [lampiranKisi('a.jpg'), lampiranKisi('b.jpg')])])[1]->penempatan[0];

    fotoDiDalamMargin($f1);
    fotoDiDalamMargin($f2);
    expect($f1->y)->toBeGreaterThanOrEqual(107.0)
        ->and($f1->y)->toBe($f2->y)
        ->and($f1->x + $f1->lebar)->toBeLessThanOrEqual($f2->x)
        ->and($f1->lebar)->toEqualWithDelta($selKisi->lebar, 0.01)
        ->and($f1->tinggi)->toEqualWithDelta($selKisi->tinggi, 0.01);
});

test('sisa lembar yang lebih pendek dari sel kisi tidak dipakai kisi', function () {
    $lembar = TataLetakPdf::susun([new BlokPdf([null, null], [lampiranKisi('f1.jpg'), lampiranKisi('f2.jpg')])]);

    expect(isiTiapLembar($lembar))->toBe([
        ['kuitansi', 'kuitansi'],
        ['f1.jpg', 'f2.jpg'],
    ]);
});

test('lampiran penuh setelah kisi pertama tidak masuk sisa lembar', function () {
    $lembar = TataLetakPdf::susun([new BlokPdf([null], [lampiranKisi('f1.jpg'), lampiranPenuh('lama.jpg')])]);

    expect(isiTiapLembar($lembar))->toBe([
        ['kuitansi', 'f1.jpg'],
        ['lama.jpg'],
    ]);
});

test('urutan lampiran dipertahankan, foto sisa lampiran penuh dan kisi berbagi lembar', function () {
    $lembar = TataLetakPdf::susun([new BlokPdf([null], [
        lampiranPenuh('undangan.jpg'),
        lampiranPenuh('hadir.jpg'),
        lampiranKisi('foto1.jpg'),
        lampiranKisi('foto2.jpg'),
        lampiranPenuh('lama.jpg'),
    ])]);

    expect(isiTiapLembar($lembar))->toBe([
        ['kuitansi', 'undangan.jpg'],
        ['hadir.jpg', 'foto1.jpg', 'foto2.jpg'],
        ['lama.jpg'],
    ]);
});

test('foto sangat panjang dan sangat lebar tetap utuh dan tidak gepeng', function (int $lebar, int $tinggi) {
    $lembar = TataLetakPdf::susun([new BlokPdf([null], [
        lampiranPenuh('sisa.jpg', $lebar, $tinggi),
        lampiranPenuh('tumpuk.jpg', $lebar, $tinggi),
        lampiranKisi('kisi.jpg', $lebar, $tinggi),
    ])]);

    $foto = collect($lembar)
        ->flatMap(fn (Lembar $l): array => $l->penempatan)
        ->filter(fn (Penempatan $p): bool => $p->jenis === 'foto');

    expect($foto)->toHaveCount(3);

    foreach ($foto as $p) {
        fotoDiDalamMargin($p);
        expect($p->lebar / $p->tinggi)->toEqualWithDelta($lebar / $tinggi, 0.01);
    }
})->with([
    'struk panjang' => [400, 4000],
    'foto melebar' => [4000, 400],
]);

test('foto dipaskan di tengah kotak', function () {
    $foto = TataLetakPdf::susun([new BlokPdf([null], [lampiranPenuh('a.jpg', 400, 4000)])])[0]->penempatan[1];

    expect($foto->x + $foto->lebar / 2)->toEqualWithDelta(105.0, 0.01)
        ->and($foto->y + $foto->tinggi / 2)->toEqualWithDelta((107.0 + 289.0) / 2, 0.01);
});

test('gaji tanpa lampiran setelah blok berlampiran mulai lembar baru', function () {
    $lembar = TataLetakPdf::susun([
        new BlokPdf([null], []),
        new BlokPdf([null], [lampiranPenuh('struk.jpg')]),
        new BlokPdf([null], []),
    ]);

    expect(isiTiapLembar($lembar))->toBe([
        ['kuitansi'],
        ['kuitansi', 'struk.jpg'],
        ['kuitansi'],
    ]);
});

test('tanpa blok tidak ada lembar', function () {
    expect(TataLetakPdf::susun([]))->toBe([]);
});
