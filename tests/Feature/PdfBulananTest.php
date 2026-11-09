<?php

use App\Actions\SusunBlokBulan;
use App\Enums\StatusManual;
use App\Enums\TataLetakBerkas;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Models\User;
use App\Pdf\BlokPdf;
use App\Pdf\LampiranPdf;
use App\Support\Terbilang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Smalot\PdfParser\Parser;

function jpegKecil(): string
{
    $gambar = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($gambar);

    return (string) ob_get_clean();
}

/**
 * JPEG 1600 px berisi derau, agar ukurannya sebanding foto HP setelah dinormalkan.
 */
function jpegBerat(): string
{
    $derau = imagecreatetruecolor(400, 300);

    for ($y = 0; $y < 300; $y++) {
        for ($x = 0; $x < 400; $x++) {
            imagesetpixel($derau, $x, $y, mt_rand(0, 0xFFFFFF));
        }
    }

    $gambar = imagecreatetruecolor(1600, 1200);
    imagecopyresized($gambar, $derau, 0, 0, 0, 0, 1600, 1200, 400, 300);
    ob_start();
    imagejpeg($gambar, null, 80);

    return (string) ob_get_clean();
}

/**
 * Nilai terlebar untuk font Helvetica (huruf W) sepanjang tepat $panjang karakter, diakhiri $akhir.
 */
function nilaiTerlebar(int $panjang, string $akhir, int $panjangKata): string
{
    $isi = implode(' ', str_split(str_repeat('W', $panjang), $panjangKata));

    return substr($isi, 0, $panjang - strlen($akhir)).$akhir;
}

function tanpaSpasi(string $teks): string
{
    return (string) preg_replace('/\s+/', '', $teks);
}

function jenisRapatPdf(): JenisKegiatan
{
    $jenis = JenisKegiatan::factory()->create();
    JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Undangan', 'urutan' => 1]);
    JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Daftar hadir', 'urutan' => 2]);
    JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Dokumentasi', 'urutan' => 3, 'tata_letak' => TataLetakBerkas::Kisi]);

    return $jenis;
}

function berkasBernama(JenisKegiatan $jenis, string $nama): JenisBerkas
{
    return $jenis->jenisBerkas()->where('nama', $nama)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $ubah
 */
function barisPdf(Sekolah $sekolah, int $bulan, array $ubah = []): Kegiatan
{
    return Kegiatan::factory()->create($ubah + ['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'bulan' => $bulan]);
}

/**
 * @param  list<Kegiatan>  $kegiatan
 * @param  array<string, mixed>  $ubah
 */
function kuitansiPdf(Sekolah $sekolah, int $nomor, array $kegiatan, array $ubah = []): Kuitansi
{
    $kuitansi = Kuitansi::factory()->create($ubah + [
        'sekolah_id' => $sekolah->id,
        'tahun' => 2026,
        'bulan' => (int) collect($kegiatan)->max('bulan'),
        'nomor_urut' => $nomor,
        'nomor' => sprintf('%03d/01/%s/2026', $nomor, $sekolah->kode_bukti),
    ]);
    $kuitansi->kegiatans()->attach(array_map(fn (Kegiatan $k): int => $k->id, $kegiatan));

    return $kuitansi;
}

function fotoPdf(Kegiatan|Acara $pemilik, ?JenisBerkas $berkas = null, ?string $path = null, int $urutan = 1, bool $simpan = true): Foto
{
    $berkas ??= JenisBerkas::factory()->create(['jenis_kegiatan_id' => $pemilik->jenis_kegiatan_id]);
    $path ??= 'foto/'.fake()->uuid().'.jpg';

    if ($simpan) {
        Storage::disk('local')->put($path, jpegKecil());
    }

    return $pemilik->fotos()->create([
        'jenis_berkas_id' => $berkas->id,
        'path' => $path,
        'lebar' => 40,
        'tinggi' => 30,
        'urutan' => $urutan,
    ]);
}

function unduhPdf(Sekolah $sekolah, int $bulan): TestResponse
{
    return test()->get(route('pdf.bulanan', ['sekolah' => $sekolah, 'tahun' => 2026, 'bulan' => $bulan]));
}

function teksPdf(TestResponse $respons): string
{
    $teks = (new Parser)->parseContent((string) $respons->getContent())->getText();

    return (string) preg_replace('/\s+/', ' ', $teks);
}

function halamanPdf(TestResponse $respons): int
{
    return count((new Parser)->parseContent((string) $respons->getContent())->getPages());
}

function isiHalamanPertama(TestResponse $respons): string
{
    preg_match('/stream\n(.*?)\nendstream/s', (string) $respons->getContent(), $cocok);

    return (string) gzuncompress($cocok[1]);
}

/**
 * Berapa kali warna garis potong dipasang pada halaman pertama.
 */
function garisPutusPdf(TestResponse $respons): int
{
    return substr_count(isiHalamanPertama($respons), '0.600 G');
}

/**
 * Jarak garis dasar teks pertama yang memuat $teks dari tepi bawah halaman pertama, dalam mm.
 */
function tinggiTeksPdf(TestResponse $respons, string $teks): float
{
    preg_match('/BT [\d.]+ ([\d.]+) Td \([^)]*'.preg_quote($teks, '/').'/', isiHalamanPertama($respons), $cocok);

    return (float) $cocok[1] * 25.4 / 72;
}

/**
 * @return list<string>
 */
function pathLampiran(BlokPdf $blok): array
{
    return array_map(fn (LampiranPdf $l): string => $l->path, $blok->lampiran);
}

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

test('tamu ditolak', function () {
    auth()->logout();

    unduhPdf(Sekolah::factory()->create(), 1)->assertRedirect(route('login'));
});

test('garis potong hanya ada bila ada isi di bawah kuitansi', function () {
    $sekolah = Sekolah::factory()->create();
    kuitansiPdf($sekolah, 1, [barisPdf($sekolah, 1)]);

    $sendiri = garisPutusPdf(unduhPdf($sekolah, 1));

    kuitansiPdf($sekolah, 2, [barisPdf($sekolah, 1)]);

    $berdua = garisPutusPdf(unduhPdf($sekolah, 1));

    expect($sendiri)->toBe(0)
        ->and($berdua)->toBeGreaterThan(0);
});

test('pdf memuat isi kuitansi dan penandatangan sekolah, urut nomor bukti', function () {
    $sekolah = Sekolah::factory()->create([
        'kode_bukti' => 'SMPG',
        'nama_kepala' => 'Fahmie Al Khudhorie',
        'nama_bendahara' => 'Dra. Nurjanah',
    ]);
    kuitansiPdf($sekolah, 12, [barisPdf($sekolah, 1)], ['jumlah' => 1_500_000, 'penerima' => 'Penyedia Internet']);
    kuitansiPdf($sekolah, 11, [barisPdf($sekolah, 1)], ['jumlah' => 3_600_000, 'penerima' => 'PLN']);

    $respons = unduhPdf($sekolah, 1);

    $respons->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('SPJ-SMPG-2026-01.pdf');

    $teks = teksPdf($respons);

    expect($teks)->toContain('Rp 3.600.000')
        ->toContain('Tiga juta enam ratus ribu rupiah')
        ->toContain('Kepala Sekolah')
        ->toContain('Fahmie Al Khudhorie')
        ->toContain('Dra. Nurjanah')
        ->toContain('PLN')
        ->and(strpos($teks, '011/01/SMPG/2026'))->toBeLessThan(strpos($teks, '012/01/SMPG/2026'));
});

test('pdf arkas memakai format B lengkap dengan kode bila ada', function () {
    $sekolah = Sekolah::factory()->create();
    kuitansiPdf($sekolah, 1, [barisPdf($sekolah, 1, ['kode' => '5.1.02.02.01.0061', 'kode_program' => '06.07.01'])], [
        'sumber_dana' => 'BOSP Reguler 2026',
        'tanggal_lunas' => '2026-01-31',
    ]);

    expect(teksPdf(unduhPdf($sekolah, 1)))->toContain('KUITANSI')
        ->toContain('Lunas 31-01-2026')
        ->toContain('BOSP Reguler 2026')
        ->toContain('5.1.02.02.01.0061')
        ->toContain('06.07.01')
        ->toContain('Bendahara')
        ->toContain('Penerima');
});

test('pdf erkam memuat semua kolom kuitansi madrasah', function () {
    $sekolah = Sekolah::factory()->erkam()->create([
        'nama' => 'MI Daarul Hikmah',
        'kode_bukti' => 'MIDH',
        'desa_kecamatan' => 'Cipondoh',
        'kabupaten' => 'Kota Tangerang',
        'provinsi' => 'Banten',
        'nama_kepala' => 'Dra. Nurjanah',
        'nama_bendahara' => 'Fahmie Al Khudhorie, S.Pd.',
    ]);
    kuitansiPdf($sekolah, 1, [barisPdf($sekolah, 1, ['tahap' => 1])], [
        'sumber_dana' => 'Dana BOS Tahap 1 2026',
        'tanggal_lunas' => '2026-01-20',
        'jumlah' => 3_600_000,
        'penerima' => 'Abdul Hamid',
    ]);

    expect(teksPdf(unduhPdf($sekolah, 1)))->toContain('KUITANSI/BUKTI PEMBAYARAN')
        ->toContain('Tahun Anggaran')
        ->toContain('Nomor Bukti')
        ->toContain('Sudah terima dari')
        ->toContain('Kepala Madrasah')
        ->toContain('Madrasah')
        ->toContain('MI Daarul Hikmah')
        ->toContain('Desa/Kecamatan')
        ->toContain('Cipondoh')
        ->toContain('Kabupaten')
        ->toContain('Kota Tangerang')
        ->toContain('Provinsi')
        ->toContain('Banten')
        ->toContain('Jumlah Uang')
        ->toContain('Rp 3.600.000')
        ->toContain('Terbilang')
        ->toContain('Tiga juta enam ratus ribu rupiah')
        ->toContain('Untuk Pembayaran')
        ->toContain('Sumber Dana')
        ->toContain('Dana BOS Tahap 1 2026')
        ->toContain('Penerima Uang')
        ->toContain('Abdul Hamid')
        ->toContain('Lunas dibayar tanggal 20-01-2026')
        ->toContain('Bendahara Madrasah')
        ->toContain('Dra. Nurjanah')
        ->toContain('Fahmie Al Khudhorie, S.Pd.');
});

test('kuitansi rapel muncul di bulan terakhir beserta foto semua bulan, urut bulan', function () {
    $sekolah = Sekolah::factory()->create();
    $jenis = jenisRapatPdf();
    $struk = berkasBernama($jenis, 'Undangan');
    $sama = ['jenis_kegiatan_id' => $jenis->id];
    $maret = barisPdf($sekolah, 3, $sama);
    $januari = barisPdf($sekolah, 1, $sama);
    $februari = barisPdf($sekolah, 2, $sama);
    fotoPdf($maret, $struk, 'foto/maret.jpg');
    fotoPdf($januari, $struk, 'foto/januari.jpg');
    fotoPdf($februari, $struk, 'foto/februari.jpg');
    $kuitansi = kuitansiPdf($sekolah, 5, [$januari, $februari, $maret]);
    kuitansiPdf($sekolah, 6, [barisPdf($sekolah, 1)]);

    $blok = SusunBlokBulan::untuk($sekolah, 2026, 3);

    expect($blok)->toHaveCount(1)
        ->and($blok[0]->kuitansi)->toHaveCount(1)
        ->and($blok[0]->kuitansi[0]->id)->toBe($kuitansi->id)
        ->and(pathLampiran($blok[0]))->toBe(['foto/januari.jpg', 'foto/februari.jpg', 'foto/maret.jpg']);

    $maretPdf = unduhPdf($sekolah, 3);
    $januariPdf = unduhPdf($sekolah, 1);

    expect(teksPdf($maretPdf))->toContain($kuitansi->nomor)
        ->and(substr_count((string) $maretPdf->getContent(), '/Subtype /Image'))->toBe(3)
        ->and(teksPdf($januariPdf))->not->toContain($kuitansi->nomor)
        ->and(substr_count((string) $januariPdf->getContent(), '/Subtype /Image'))->toBe(0);
});

test('baris yang dirapel ke bulan lain tidak membuat blok, juga di bulan februari', function () {
    $sekolah = Sekolah::factory()->create();
    $januari = barisPdf($sekolah, 1);
    $februari = barisPdf($sekolah, 2);
    fotoPdf($februari);
    kuitansiPdf($sekolah, 5, [$januari, barisPdf($sekolah, 3), $februari]);

    expect(SusunBlokBulan::untuk($sekolah, 2026, 1))->toBe([])
        ->and(SusunBlokBulan::untuk($sekolah, 2026, 2))->toBe([])
        ->and(SusunBlokBulan::untuk($sekolah, 2026, 3))->toHaveCount(1);
});

test('kegiatan bertanda manual tidak muncul', function (StatusManual $status) {
    $sekolah = Sekolah::factory()->create();
    $tampil = kuitansiPdf($sekolah, 1, [barisPdf($sekolah, 1)]);
    $ditandai = barisPdf($sekolah, 1, ['status_manual' => $status]);
    fotoPdf($ditandai, path: 'foto/ditandai.jpg');
    $tersembunyi = kuitansiPdf($sekolah, 2, [$ditandai]);

    $respons = unduhPdf($sekolah, 1);

    expect(teksPdf($respons))->toContain($tampil->nomor)
        ->not->toContain($tersembunyi->nomor)
        ->and(substr_count((string) $respons->getContent(), '/Subtype /Image'))->toBe(0);
})->with([StatusManual::DiLuarAplikasi, StatusManual::TidakDilaksanakan]);

test('kegiatan tanpa kuitansi dan tanpa foto tidak ikut, yang berfoto tampil sebagai kuitansi belum dibuat di akhir', function () {
    $sekolah = Sekolah::factory()->create();
    barisPdf($sekolah, 1);
    $berfoto = barisPdf($sekolah, 1);
    fotoPdf($berfoto);
    $berkuitansi = kuitansiPdf($sekolah, 9, [barisPdf($sekolah, 1)]);

    $blok = SusunBlokBulan::untuk($sekolah, 2026, 1);

    expect($blok)->toHaveCount(2)
        ->and($blok[0]->kuitansi[0]->id)->toBe($berkuitansi->id)
        ->and($blok[1]->kuitansi)->toBe([null])
        ->and($blok[1]->lampiran)->toHaveCount(1)
        ->and(teksPdf(unduhPdf($sekolah, 1)))->toContain('KUITANSI BELUM DIBUAT');
});

test('satu acara satu blok, dokumen bersama hanya dicetak sekali', function () {
    $sekolah = Sekolah::factory()->create();
    $jenis = jenisRapatPdf();
    $acara = Acara::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'bulan' => 1, 'jenis_kegiatan_id' => $jenis->id]);
    $sama = ['acara_id' => $acara->id, 'jenis_kegiatan_id' => $jenis->id];
    $snack = barisPdf($sekolah, 1, $sama);
    $nasi = barisPdf($sekolah, 1, $sama);
    fotoPdf($acara, berkasBernama($jenis, 'Undangan'), 'foto/undangan.jpg');
    fotoPdf($acara, berkasBernama($jenis, 'Daftar hadir'), 'foto/hadir.jpg');
    fotoPdf($acara, berkasBernama($jenis, 'Daftar hadir'), 'foto/hadir2.jpg', 2);
    kuitansiPdf($sekolah, 4, [$snack]);
    kuitansiPdf($sekolah, 3, [$nasi]);
    $lain = kuitansiPdf($sekolah, 2, [barisPdf($sekolah, 1)]);

    $blok = SusunBlokBulan::untuk($sekolah, 2026, 1);

    expect($blok)->toHaveCount(2)
        ->and($blok[0]->kuitansi[0]->id)->toBe($lain->id)
        ->and(array_map(fn (Kuitansi $k): int => $k->nomor_urut, $blok[1]->kuitansi))->toBe([3, 4])
        ->and(pathLampiran($blok[1]))->toBe(['foto/undangan.jpg', 'foto/hadir.jpg', 'foto/hadir2.jpg']);

    $respons = unduhPdf($sekolah, 1);

    // Lembar 1: kuitansi lain. Lembar 2: 2 kuitansi + undangan. Lembar 3: 2 daftar hadir.
    expect(halamanPdf($respons))->toBe(3)
        ->and(substr_count((string) $respons->getContent(), '/Subtype /Image'))->toBe(3);
});

test('baris acara tanpa kuitansi tampil sebagai kuitansi belum dibuat setelah yang sudah ada', function () {
    $sekolah = Sekolah::factory()->create();
    $jenis = jenisRapatPdf();
    $acara = Acara::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'bulan' => 1, 'jenis_kegiatan_id' => $jenis->id]);
    $sama = ['acara_id' => $acara->id, 'jenis_kegiatan_id' => $jenis->id];
    barisPdf($sekolah, 1, $sama);
    $kuitansi = kuitansiPdf($sekolah, 3, [barisPdf($sekolah, 1, $sama)]);
    fotoPdf($acara, berkasBernama($jenis, 'Undangan'));

    $blok = SusunBlokBulan::untuk($sekolah, 2026, 1);

    expect($blok)->toHaveCount(1)
        ->and($blok[0]->kuitansi[0]->id)->toBe($kuitansi->id)
        ->and($blok[0]->kuitansi[1])->toBeNull();
});

test('lampiran mengikuti urutan jenis berkas, foto dari jenis lama paling akhir', function () {
    $sekolah = Sekolah::factory()->create();
    $jenis = jenisRapatPdf();
    $kegiatan = barisPdf($sekolah, 1, ['jenis_kegiatan_id' => $jenis->id]);
    $berkasLama = JenisBerkas::factory()->create(['tata_letak' => TataLetakBerkas::Kisi]);
    fotoPdf($kegiatan, $berkasLama, 'foto/lama.jpg');
    fotoPdf($kegiatan, berkasBernama($jenis, 'Dokumentasi'), 'foto/dok2.jpg', 2);
    fotoPdf($kegiatan, berkasBernama($jenis, 'Dokumentasi'), 'foto/dok1.jpg', 1);
    fotoPdf($kegiatan, berkasBernama($jenis, 'Daftar hadir'), 'foto/hadir.jpg');
    fotoPdf($kegiatan, berkasBernama($jenis, 'Undangan'), 'foto/undangan.jpg');

    $lampiran = SusunBlokBulan::untuk($sekolah, 2026, 1)[0]->lampiran;

    expect(array_map(fn (LampiranPdf $l): string => $l->path, $lampiran))
        ->toBe(['foto/undangan.jpg', 'foto/hadir.jpg', 'foto/dok1.jpg', 'foto/dok2.jpg', 'foto/lama.jpg'])
        ->and($lampiran[0]->tataLetak)->toBe(TataLetakBerkas::Penuh)
        ->and($lampiran[2]->tataLetak)->toBe(TataLetakBerkas::Kisi)
        ->and($lampiran[4]->tataLetak)->toBe(TataLetakBerkas::Kisi);
});

test('baris hilang dari anggaran yang berberkas tetap masuk', function () {
    $sekolah = Sekolah::factory()->create();
    $kuitansi = kuitansiPdf($sekolah, 1, [barisPdf($sekolah, 1, ['hilang_dari_anggaran' => true])]);

    expect(teksPdf(unduhPdf($sekolah, 1)))->toContain($kuitansi->nomor);
});

test('pdf tidak bercampur sekolah lain atau tahun lain', function () {
    $sekolah = Sekolah::factory()->create(['nama_kepala' => 'Kepala Benar']);
    $lain = Sekolah::factory()->create(['nama_kepala' => 'Kepala Lain']);
    $milik = kuitansiPdf($sekolah, 1, [barisPdf($sekolah, 1)]);
    $bukan = kuitansiPdf($lain, 2, [barisPdf($lain, 1)]);
    $tahunLain = Kuitansi::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2025, 'bulan' => 1, 'nomor' => '777/01/X/2025']);
    $tahunLain->kegiatans()->attach(barisPdf($sekolah, 1, ['tahun' => 2025]));

    expect(teksPdf(unduhPdf($sekolah, 1)))->toContain($milik->nomor)
        ->toContain('Kepala Benar')
        ->not->toContain($bukan->nomor)
        ->not->toContain('Kepala Lain')
        ->not->toContain('777/01/X/2025');
});

test('bulan tanpa isi kembali ke layar bulan dengan pesan, bukan pdf kosong', function () {
    $sekolah = Sekolah::factory()->create();
    barisPdf($sekolah, 2);

    unduhPdf($sekolah, 2)->assertRedirect(route('dashboard', ['bulan' => '2026-02']));

    $this->get(route('dashboard', ['bulan' => '2026-02']))
        ->assertInertia(fn (Assert $page) => $page->hasFlash('toast', [
            'type' => 'warning',
            'message' => 'Belum ada berkas untuk bulan ini',
        ]));
});

test('file foto hilang dari disk tidak menggagalkan pdf', function () {
    $sekolah = Sekolah::factory()->create();
    $kegiatan = barisPdf($sekolah, 1);
    kuitansiPdf($sekolah, 1, [$kegiatan]);
    fotoPdf($kegiatan, simpan: false);

    $respons = unduhPdf($sekolah, 1);

    $respons->assertOk();
    expect(teksPdf($respons))->toContain('Foto tidak ditemukan');
});

test('file foto rusak tidak menggagalkan pdf', function () {
    $sekolah = Sekolah::factory()->create();
    $kegiatan = barisPdf($sekolah, 1);
    kuitansiPdf($sekolah, 1, [$kegiatan]);
    fotoPdf($kegiatan, path: 'foto/rusak.jpg');
    Storage::disk('local')->put('foto/rusak.jpg', 'bukan gambar');

    $respons = unduhPdf($sekolah, 1);

    $respons->assertOk();
    expect(teksPdf($respons))->toContain('Foto tidak ditemukan');
});

test('kode bukti dengan garis miring tetap menghasilkan nama file sah', function () {
    $sekolah = Sekolah::factory()->create(['kode_bukti' => 'MI/DH']);
    kuitansiPdf($sekolah, 1, [barisPdf($sekolah, 3)]);

    unduhPdf($sekolah, 3)->assertOk()->assertDownload('SPJ-MI-DH-2026-03.pdf');
});

test('nilai lama yang melewati batas dipotong sebagai pertahanan terakhir, karakter asing tidak menggagalkan pdf', function (bool $erkam) {
    $pabrik = Sekolah::factory();
    $sekolah = ($erkam ? $pabrik->erkam() : $pabrik)->create(['nama_kepala' => 'Kepala Tetap Tercetak']);
    kuitansiPdf($sekolah, 1, [barisPdf($sekolah, 1)], [
        'untuk_pembayaran' => 'Konsumsi rapat',
        'penerima' => "عبد الله \u{1F600} ".str_repeat('Nama Panjang ', 19),
    ]);

    $respons = unduhPdf($sekolah, 1);

    $respons->assertOk();
    expect(teksPdf($respons))->toContain('Kepala Tetap Tercetak')
        ->toContain('Konsumsi rapat')
        ->toContain('...');
})->with(['arkas' => false, 'erkam' => true]);

test('nilai terpanjang yang sah tercetak utuh tanpa dipotong dan tanda tangan tetap lega', function (bool $erkam, int $panjangKata) {
    $sekolah = ($erkam ? Sekolah::factory()->erkam() : Sekolah::factory())->create();
    $nilaiSekolah = [
        'nama' => nilaiTerlebar(100, 'AKHIRSEKOLAH', $panjangKata),
        'sebutan' => nilaiTerlebar(20, 'SEBUT', $panjangKata),
        'desa_kecamatan' => nilaiTerlebar(100, 'AKHIRDESA', $panjangKata),
        'kabupaten' => nilaiTerlebar(100, 'AKHIRKAB', $panjangKata),
        'provinsi' => nilaiTerlebar(100, 'AKHIRPROV', $panjangKata),
        'nama_kepala' => nilaiTerlebar(90, 'AKHIRKEPALA', $panjangKata),
        'nama_bendahara' => nilaiTerlebar(90, 'AKHIRBENDAHARA', $panjangKata),
    ];
    $nilaiKuitansi = [
        'nomor' => '123456789/'.nilaiTerlebar(30, 'AKHIRNOMOR', $panjangKata),
        'untuk_pembayaran' => nilaiTerlebar(200, 'AKHIRUNTUK', $panjangKata),
        'penerima' => nilaiTerlebar(90, 'AKHIRPENERIMA', $panjangKata),
    ];

    $this->patch(route('sekolah.update', $sekolah), $nilaiSekolah)->assertSessionHasNoErrors();
    $this->post(route('kuitansi.store', barisPdf($sekolah, 1)), $nilaiKuitansi + [
        'tanggal_lunas' => '2026-01-31',
        'jumlah' => 999_999_999_999,
    ])->assertSessionHasNoErrors();
    // Angka urut diatur ulang saat simpan; paksa nomor terlebar untuk menguji tata letak.
    Kuitansi::query()->update(['nomor' => $nilaiKuitansi['nomor']]);

    $respons = unduhPdf($sekolah, 1);
    $teks = teksPdf($respons);
    $rapat = tanpaSpasi($teks);

    $tercetak = $erkam
        ? [...$nilaiSekolah, ...$nilaiKuitansi]
        : ['sebutan' => $nilaiSekolah['sebutan'], 'nama_kepala' => $nilaiSekolah['nama_kepala'], 'nama_bendahara' => $nilaiSekolah['nama_bendahara'], ...$nilaiKuitansi];

    foreach ($tercetak as $nilai) {
        expect($rapat)->toContain(tanpaSpasi($nilai));
    }

    expect($teks)->toContain('Rp 999.999.999.999')
        ->not->toContain('...')
        ->and($rapat)->toContain(tanpaSpasi(Terbilang::rupiah(999_999_999_999)));

    // Jabatan ke baris terakhir nama (paling banyak tiga baris): tempat tanda tangan tetap tersisa.
    $jabatan = tinggiTeksPdf($respons, 'Bendahara');
    $nama = tinggiTeksPdf($respons, 'AKHIRBENDAHARA');

    expect($jabatan - $nama)->toBeGreaterThanOrEqual(19.0)
        ->and($jabatan)->toBeLessThan(tinggiTeksPdf($respons, $erkam ? 'Sumber Dana' : 'Kode'));
})->with(['arkas' => false, 'erkam' => true])->with([
    'kata pendek' => 4,
    'kata panjang' => 16,
    'tanpa spasi' => 1000,
]);

test('bulan di luar rentang tidak ditemukan', function (int $bulan) {
    unduhPdf(Sekolah::factory()->create(), $bulan)->assertNotFound();
})->with([0, 13]);

test('uji beban: 60 foto 1600 px dalam satu bulan tetap di bawah 256 MB', function () {
    $sekolah = Sekolah::factory()->create();
    $jenis = jenisRapatPdf();
    $berkas = berkasBernama($jenis, 'Undangan');
    $jpeg = jpegBerat();

    for ($i = 0; $i < 20; $i++) {
        $kegiatan = barisPdf($sekolah, 1, ['jenis_kegiatan_id' => $jenis->id]);
        kuitansiPdf($sekolah, $i + 1, [$kegiatan]);

        for ($j = 1; $j <= 3; $j++) {
            // Tiap foto punya path sendiri: FPDF hanya menyematkan satu kali per path.
            $path = "foto/beban-{$i}-{$j}.jpg";
            Storage::disk('local')->put($path, $jpeg);
            $kegiatan->fotos()->create(['jenis_berkas_id' => $berkas->id, 'path' => $path, 'lebar' => 1600, 'tinggi' => 1200, 'urutan' => $j]);
        }
    }

    memory_reset_peak_usage();
    $respons = unduhPdf($sekolah, 1);
    $puncak = memory_get_peak_usage(true);

    $respons->assertOk();
    expect($puncak)->toBeLessThan(256 * 1024 * 1024)
        ->and(substr_count((string) $respons->getContent(), '/Subtype /Image'))->toBe(60);

    Storage::disk('local')->deleteDirectory('foto');
});
