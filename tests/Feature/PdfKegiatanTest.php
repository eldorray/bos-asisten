<?php

use App\Actions\HitungStatusKegiatan;
use App\Actions\SusunBlokBulan;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * @return array{0: Kegiatan, 1: JenisKegiatan}
 */
function kegiatanBelanja(): array
{
    $jenis = JenisKegiatan::factory()->create();
    JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Nota', 'urutan' => 1]);
    JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Foto barang', 'urutan' => 2]);

    $sekolah = Sekolah::factory()->create();
    $kegiatan = Kegiatan::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'bulan' => 3, 'jenis_kegiatan_id' => $jenis->id]);

    return [$kegiatan, $jenis];
}

function pasangKuitansi(Kegiatan $kegiatan): void
{
    $kuitansi = Kuitansi::factory()->create(['sekolah_id' => $kegiatan->sekolah_id, 'tahun' => 2026, 'bulan' => 3, 'nomor_urut' => 1]);
    $kuitansi->kegiatans()->attach($kegiatan->id);
}

function pasangFoto(Kegiatan $kegiatan, string $namaBerkas): string
{
    $berkas = JenisBerkas::where('jenis_kegiatan_id', $kegiatan->jenis_kegiatan_id)->where('nama', $namaBerkas)->firstOrFail();
    $path = 'foto/'.fake()->uuid().'.jpg';
    $gambar = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($gambar);
    Storage::disk('local')->put($path, (string) ob_get_clean());
    $kegiatan->fotos()->create(['jenis_berkas_id' => $berkas->id, 'path' => $path, 'lebar' => 40, 'tinggi' => 30, 'urutan' => 1]);

    return $path;
}

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

test('kegiatan lengkap bisa diunduh sebagai pdf', function () {
    [$kegiatan] = kegiatanBelanja();
    pasangKuitansi($kegiatan);
    pasangFoto($kegiatan, 'Nota');
    pasangFoto($kegiatan, 'Foto barang');

    $respons = $this->get(route('pdf.kegiatan', $kegiatan))->assertOk();

    expect($respons->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respons->headers->get('Content-Disposition'))->toContain('attachment')
        ->and(substr((string) $respons->getContent(), 0, 4))->toBe('%PDF');
});

test('kegiatan kurang satu berkas masih bisa diunduh sebagai draf', function () {
    [$kegiatan] = kegiatanBelanja();
    pasangKuitansi($kegiatan);
    pasangFoto($kegiatan, 'Nota');

    $this->get(route('pdf.kegiatan', $kegiatan))->assertOk();
});

test('kegiatan kurang dua berkas atau lebih ditolak', function () {
    [$kegiatan] = kegiatanBelanja();
    pasangFoto($kegiatan, 'Nota');

    $this->get(route('pdf.kegiatan', $kegiatan))->assertRedirect(route('kegiatan.show', $kegiatan));
});

test('urutan isi pdf kuitansi, nota, lalu foto', function () {
    [$kegiatan] = kegiatanBelanja();
    pasangKuitansi($kegiatan);
    $foto = pasangFoto($kegiatan, 'Foto barang');
    $nota = pasangFoto($kegiatan, 'Nota');

    $kegiatan->load([...HitungStatusKegiatan::RELASI, 'fotos.jenisBerkas', 'acara.fotos.jenisBerkas']);
    $blok = SusunBlokBulan::untukKegiatan($kegiatan);

    expect($blok->kuitansi)->toHaveCount(1)
        ->and(array_map(fn ($l) => $l->path, $blok->lampiran))->toBe([$nota, $foto]);
});

test('halaman rincian memberi tahu kegiatan bisa diunduh', function () {
    [$kegiatan] = kegiatanBelanja();
    pasangKuitansi($kegiatan);
    pasangFoto($kegiatan, 'Nota');

    $this->get(route('kegiatan.show', $kegiatan))
        ->assertInertia(fn ($page) => $page->where('kegiatan.bisaUnduhPdf', true));
});

test('tamu ditolak', function () {
    auth()->logout();
    [$kegiatan] = kegiatanBelanja();

    $this->get(route('pdf.kegiatan', $kegiatan))->assertRedirect(route('login'));
});

test('dashboard menandai kegiatan selesai bisa diunduh', function () {
    [$kegiatan] = kegiatanBelanja();
    pasangKuitansi($kegiatan);
    pasangFoto($kegiatan, 'Nota');
    pasangFoto($kegiatan, 'Foto barang');

    $this->get(route('dashboard', ['bulan' => '2026-03']))
        ->assertInertia(fn ($page) => $page
            ->where('sekolah.0.kegiatan.0.status', 'selesai')
            ->where('sekolah.0.kegiatan.0.bisaUnduhPdf', true));
});

test('pdf kegiatan memuat kuitansi satu halaman penuh lalu lampiran', function () {
    [$kegiatan] = kegiatanBelanja();
    pasangKuitansi($kegiatan);
    pasangFoto($kegiatan, 'Nota');
    pasangFoto($kegiatan, 'Foto barang');

    $isi = (string) $this->get(route('pdf.kegiatan', $kegiatan))->getContent();
    $pdf = (new Parser)->parseContent($isi);
    $halaman = $pdf->getPages();

    expect($halaman)->toHaveCount(3)
        ->and($halaman[0]->getText())->toContain('KUITANSI/BUKTI PEMBAYARAN')->toContain('Sumber Dana')->toContain('Lunas dibayar tanggal')
        ->and($halaman[1]->getText())->not->toContain('KUITANSI');
});
