<?php

use App\Enums\SistemAnggaran;
use App\Enums\StatusManual;
use App\Impor\AnggaranTerbaca;
use App\Impor\BarisAnggaran;
use App\Impor\SimpanAnggaran;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\ImporAnggaran;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function baruFoto(Filesystem $disk): array
{
    $jenis = JenisKegiatan::factory()->create();
    $berkas = JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id]);
    $kegiatan = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id]);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    return [route('foto.store', $kegiatan), ['jenis_berkas_id' => $berkas->id, 'foto' => UploadedFile::fake()->image('a.jpg', 100, 100)]];
}

test('foto yang gagal ditulis ke disk ditolak dan tidak dicatat', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('putFileAs')->andReturn(false);

    [$url, $data] = baruFoto($disk);

    $this->post($url, $data)->assertSessionHasErrors(['foto' => 'Foto gagal disimpan. Coba lagi.']);
    expect(Foto::count())->toBe(0);
});

test('foto yang tertulis sebagian dihapus dan tidak dicatat', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('putFileAs')->andReturn('foto/x.jpg');
    $disk->shouldReceive('size')->andReturn(1);
    $disk->shouldReceive('delete')->once();

    [$url, $data] = baruFoto($disk);

    $this->post($url, $data)->assertSessionHasErrors(['foto' => 'Foto gagal disimpan. Coba lagi.']);
    expect(Foto::count())->toBe(0);
});

test('revisi RKAM tidak memindahkan kuitansi ke baris tahap lain', function () {
    JenisKegiatan::factory()->create(['cadangan' => true]);
    $sekolah = Sekolah::factory()->erkam()->create();
    $spidol = fn (int $tahap): BarisAnggaran => new BarisAnggaran('8.2.1', null, 'Tinta spidol', '10 buah', 10000, 0, 100000, $tahap);
    $rkam = fn (array $baris): AnggaranTerbaca => new AnggaranTerbaca(SistemAnggaran::Erkam, $sekolah->identitas, 2026, null, 'APBN - BOS', $baris, 0);

    $simpan = app(SimpanAnggaran::class);
    $simpan->simpan($rkam([$spidol(1)]), 'awal.pdf');
    $baris = Kegiatan::sole();
    $baris->update(['bulan' => 3]);

    $hasil = $simpan->simpan($rkam([$spidol(2), $spidol(1)]), 'revisi.pdf');

    expect($hasil->baru)->toBe(1)->and($hasil->berubah)->toBe(0)
        ->and($baris->fresh()->tahap)->toBe(1)
        ->and($baris->fresh()->bulan)->toBe(3)
        ->and(Kegiatan::where('tahap', 2)->sole()->bulan)->toBeNull();
});

test('pemilih bulan menandai bulan lampau yang PDF-nya belum diunggah', function () {
    $this->travelTo('2026-04-15');
    $smp = Sekolah::factory()->create();
    $mi = Sekolah::factory()->erkam()->create();
    ImporAnggaran::factory()->create(['sekolah_id' => $mi->id, 'tahun' => 2026, 'periode' => 0]);

    foreach ([1, 3] as $bulan) {
        ImporAnggaran::factory()->create(['sekolah_id' => $smp->id, 'tahun' => 2026, 'periode' => $bulan]);
    }

    $this->get('/dashboard?bulan=2026-04')->assertInertia(fn (Assert $page) => $page
        ->where('pdfBelumPerBulan.1', false)
        ->where('pdfBelumPerBulan.2', true)
        ->where('pdfBelumPerBulan.3', false)
        ->where('pdfBelumPerBulan.4', true)
        ->where('pdfBelumPerBulan.5', false));

    $this->get('/dashboard?bulan=2027-02')->assertInertia(fn (Assert $page) => $page
        ->where('pdfBelumPerBulan.1', false)
        ->where('pdfBelumPerBulan.2', false));
});

test('baris tanpa bulan yang sudah ditandai manual keluar dari kotak belum berbulan', function () {
    $mi = Sekolah::factory()->erkam()->create();
    Kegiatan::factory()->create(['sekolah_id' => $mi->id, 'tahun' => 2026, 'bulan' => null, 'tahap' => 1, 'status_manual' => StatusManual::TidakDilaksanakan, 'catatan_status' => 'batal']);

    $this->get('/dashboard?bulan=2026-01')->assertInertia(fn (Assert $page) => $page->where('sekolah.0.belumBerbulan', []));
});

test('halaman rincian memberi pilihan bulan hanya untuk baris yang boleh dipindah', function () {
    $mi = Sekolah::factory()->erkam()->create();
    $smp = Sekolah::factory()->create();
    $bebas = Kegiatan::factory()->create(['sekolah_id' => $mi->id, 'bulan' => 3, 'tahap' => 2]);
    $arkas = Kegiatan::factory()->create(['sekolah_id' => $smp->id, 'bulan' => 3, 'tahap' => null]);
    $berkuitansi = Kegiatan::factory()->create(['sekolah_id' => $mi->id, 'bulan' => 3, 'tahap' => 1]);
    $berkuitansi->kuitansi()->attach(Kuitansi::factory()->create(['sekolah_id' => $mi->id, 'tahun' => $berkuitansi->tahun, 'bulan' => 3]));
    $beracara = Kegiatan::factory()->create(['sekolah_id' => $mi->id, 'bulan' => 3, 'tahap' => 1, 'acara_id' => Acara::factory()->create()->id]);

    $this->get(route('kegiatan.show', $bebas))->assertInertia(fn (Assert $page) => $page->where('pilihanBulan', [7, 8, 9, 10, 11, 12]));

    foreach ([$arkas, $berkuitansi, $beracara] as $terkunci) {
        $this->get(route('kegiatan.show', $terkunci))->assertInertia(fn (Assert $page) => $page->where('pilihanBulan', []));
    }
});

test('jenis tidak bisa diubah selama baris punya foto', function () {
    $jenis = JenisKegiatan::factory()->create();
    $lain = JenisKegiatan::factory()->create();
    $kegiatan = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id]);
    $kosong = Kegiatan::factory()->create(['jenis_kegiatan_id' => $jenis->id]);
    Foto::factory()->create([
        'pemilik_type' => $kegiatan->getMorphClass(),
        'pemilik_id' => $kegiatan->id,
        'jenis_berkas_id' => JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id])->id,
    ]);

    $this->patch(route('kegiatan.update', $kegiatan), ['jenis_kegiatan_id' => $lain->id])
        ->assertSessionHasErrors(['jenis_kegiatan_id' => 'Kegiatan ini sudah punya foto berkas. Hapus fotonya dulu sebelum mengubah jenis.']);
    expect($kegiatan->fresh()->jenis_kegiatan_id)->toBe($jenis->id);

    $this->patch(route('kegiatan.update', $kosong), ['jenis_kegiatan_id' => $lain->id])->assertSessionHasNoErrors();
});

test('rincian menawarkan baris yang boleh digabung jadi satu acara', function () {
    $sekolah = Sekolah::factory()->create();
    $jenis = JenisKegiatan::factory()->create();
    $atur = ['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'bulan' => 3, 'jenis_kegiatan_id' => $jenis->id];

    $ini = Kegiatan::factory()->create($atur);
    $boleh = Kegiatan::factory()->create($atur + ['nama' => 'Snack rapat', 'jumlah' => 250000]);
    Kegiatan::factory()->create([...$atur, 'bulan' => 4]);
    Kegiatan::factory()->create([...$atur, 'jenis_kegiatan_id' => JenisKegiatan::factory()->create()->id]);
    Kegiatan::factory()->create($atur + ['status_manual' => StatusManual::DiLuarAplikasi]);
    Kegiatan::factory()->create($atur + ['hilang_dari_anggaran' => true]);
    Kegiatan::factory()->create($atur + ['acara_id' => Acara::factory()->create()->id]);

    $this->get(route('kegiatan.show', $ini))->assertInertia(fn (Assert $page) => $page
        ->where('kandidatAcara', [['id' => $boleh->id, 'nama' => 'Snack rapat', 'jumlah' => 250000]]));
});

test('rincian memberi tahu kuitansi yang berbeda dari anggaran', function () {
    $kegiatan = Kegiatan::factory()->create(['bulan' => 3, 'jumlah' => 200000]);
    $kuitansi = Kuitansi::factory()->create(['sekolah_id' => $kegiatan->sekolah_id, 'tahun' => $kegiatan->tahun, 'bulan' => 3, 'jumlah' => 100000]);
    $kegiatan->kuitansi()->attach($kuitansi);

    $this->get(route('kegiatan.show', $kegiatan))->assertInertia(fn (Assert $page) => $page
        ->where('selisihAnggaran', ['kuitansi' => 100000, 'anggaran' => 200000]));

    $kuitansi->update(['jumlah' => 200000]);

    $this->get(route('kegiatan.show', $kegiatan))->assertInertia(fn (Assert $page) => $page->where('selisihAnggaran', null));
});
