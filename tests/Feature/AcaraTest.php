<?php

use App\Actions\GabungAcara;
use App\Actions\HitungStatusKegiatan;
use App\Actions\SaranAcara;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: JenisKegiatan, 1: JenisBerkas, 2: JenisBerkas}
 */
function jenisRapat(): array
{
    $jenis = JenisKegiatan::factory()->create(['nama' => 'Rapat']);
    $undangan = JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Undangan', 'urutan' => 1]);
    $absen = JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Daftar hadir', 'urutan' => 2]);

    return [$jenis, $undangan, $absen];
}

/**
 * @param  array<string, mixed>  $ubah
 */
function barisRapat(Sekolah $sekolah, JenisKegiatan $jenis, string $nama, array $ubah = []): Kegiatan
{
    return Kegiatan::factory()->create($ubah + [
        'sekolah_id' => $sekolah->id,
        'tahun' => 2026,
        'bulan' => 3,
        'kode' => '5.1.02.01.01.0024',
        'kode_program' => '02.07',
        'nama' => $nama,
        'uraian' => $nama,
        'jenis_kegiatan_id' => $jenis->id,
    ]);
}

function fotoUntuk(Kegiatan|Acara $pemilik, JenisBerkas $berkas, int $urutan): Foto
{
    $foto = Foto::factory()->create([
        'pemilik_type' => $pemilik::class,
        'pemilik_id' => $pemilik->id,
        'jenis_berkas_id' => $berkas->id,
        'urutan' => $urutan,
    ]);
    Storage::disk('local')->put($foto->path, 'jpeg');

    return $foto;
}

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

test('jamuan makan dan ringan di bulan yang sama disarankan tapi tidak tergabung otomatis', function () {
    [$jenis] = jenisRapat();
    $sekolah = Sekolah::factory()->create();
    $makan = barisRapat($sekolah, $jenis, 'Jamuan Makan Rapat');
    $ringan = barisRapat($sekolah, $jenis, 'Jamuan Ringan Rapat');
    barisRapat($sekolah, $jenis, 'Honor lain', ['kode_program' => '09.09']);
    barisRapat($sekolah, JenisKegiatan::factory()->create(['nama' => 'Listrik']), 'Listrik');

    $saran = SaranAcara::untukBulan($sekolah, 2026, 3);

    expect($saran)->toHaveCount(1)
        ->and(collect($saran[0])->pluck('id')->all())->toBe([$makan->id, $ringan->id])
        ->and(Acara::count())->toBe(0)
        ->and($makan->fresh()->acara_id)->toBeNull();

    $this->get(route('dashboard', ['bulan' => '2026-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sekolah.0.saranAcara.0.ids', [$makan->id, $ringan->id])
            ->where('sekolah.0.saranAcara.0.nama', 'Jamuan Makan Rapat'));
});

test('saran erkam memakai kode dan melewati baris yang sudah beracara', function () {
    [$jenis] = jenisRapat();
    $sekolah = Sekolah::factory()->erkam()->create();
    $a = barisRapat($sekolah, $jenis, 'Konsumsi A', ['kode_program' => null, 'tahap' => 1]);
    $b = barisRapat($sekolah, $jenis, 'Konsumsi B', ['kode_program' => null, 'tahap' => 1]);
    $c = barisRapat($sekolah, $jenis, 'Konsumsi C', ['kode_program' => null, 'tahap' => 1, 'kode' => '5.9']);

    expect(collect(SaranAcara::untukBulan($sekolah, 2026, 3)[0])->pluck('id')->all())->toBe([$a->id, $b->id]);

    app(GabungAcara::class)->gabung([$a->id, $b->id], 'Rapat');

    expect(SaranAcara::untukBulan($sekolah, 2026, 3))->toBe([])
        ->and($c->fresh()->acara_id)->toBeNull();
});

test('gabung memindahkan foto ke acara dengan urutan stabil', function () {
    [$jenis, $undangan, $absen] = jenisRapat();
    $sekolah = Sekolah::factory()->create();
    $makan = barisRapat($sekolah, $jenis, 'Jamuan Makan');
    $ringan = barisRapat($sekolah, $jenis, 'Jamuan Ringan');
    $f1 = fotoUntuk($makan, $undangan, 1);
    $f2 = fotoUntuk($makan, $undangan, 2);
    $f3 = fotoUntuk($ringan, $undangan, 1);
    $f4 = fotoUntuk($ringan, $absen, 1);
    $jalur = [$f1, $f2, $f3, $f4];

    $this->post(route('acara.store'), ['kegiatan_ids' => [$ringan->id, $makan->id], 'nama' => 'Rapat Maret'])
        ->assertSessionHasNoErrors();

    $acara = Acara::firstOrFail();
    expect($acara->nama)->toBe('Rapat Maret')
        ->and($acara->jenis_kegiatan_id)->toBe($jenis->id)
        ->and($acara->sekolah_id)->toBe($sekolah->id)
        ->and([$acara->tahun, $acara->bulan])->toBe([2026, 3])
        ->and($makan->fresh()->acara_id)->toBe($acara->id)
        ->and($ringan->fresh()->acara_id)->toBe($acara->id)
        ->and(Foto::count())->toBe(4)
        ->and(Foto::where('pemilik_type', Kegiatan::class)->count())->toBe(0)
        ->and($acara->fotos()->where('jenis_berkas_id', $undangan->id)->orderBy('urutan')->pluck('id')->all())
        ->toBe([$f1->id, $f2->id, $f3->id])
        ->and($acara->fotos()->where('jenis_berkas_id', $undangan->id)->orderBy('urutan')->pluck('urutan')->all())
        ->toBe([1, 2, 3])
        ->and($f4->fresh()->urutan)->toBe(1);

    foreach ($jalur as $foto) {
        Storage::disk('local')->assertExists($foto->path);
    }
});

test('unggah undangan untuk satu baris membuat berkas kedua baris ada', function () {
    [$jenis, $undangan] = jenisRapat();
    $sekolah = Sekolah::factory()->create();
    $makan = barisRapat($sekolah, $jenis, 'Jamuan Makan');
    $ringan = barisRapat($sekolah, $jenis, 'Jamuan Ringan');
    app(GabungAcara::class)->gabung([$makan->id, $ringan->id], 'Rapat Maret');

    $gambar = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($gambar);
    $jpeg = (string) ob_get_clean();

    $this->post(route('foto.store', $makan), [
        'jenis_berkas_id' => $undangan->id,
        'foto' => UploadedFile::fake()->createWithContent('undangan.jpg', $jpeg),
    ])->assertSessionHasNoErrors();

    foreach ([$makan, $ringan] as $baris) {
        $status = HitungStatusKegiatan::untuk($baris->fresh(HitungStatusKegiatan::RELASI));
        $berkas = collect($status->berkas)->keyBy('nama');

        expect($berkas['Undangan']['ada'])->toBeTrue()
            ->and($berkas['Daftar hadir']['ada'])->toBeFalse();
    }
});

test('tiap baris tetap butuh kuitansi sendiri', function () {
    [$jenis] = jenisRapat();
    $sekolah = Sekolah::factory()->create();
    $makan = barisRapat($sekolah, $jenis, 'Jamuan Makan');
    $ringan = barisRapat($sekolah, $jenis, 'Jamuan Ringan');
    app(GabungAcara::class)->gabung([$makan->id, $ringan->id], 'Rapat Maret');

    $this->post(route('kuitansi.store', $makan), [
        'nomor' => '001/03/SMPG/2026',
        'untuk_pembayaran' => 'Jamuan makan rapat',
        'penerima' => 'Warung A',
        'tanggal_lunas' => '2026-03-10',
        'jumlah' => $makan->jumlah,
    ])->assertSessionHasNoErrors();

    $ada = fn (Kegiatan $k): bool => collect(HitungStatusKegiatan::untuk($k->fresh(HitungStatusKegiatan::RELASI))->berkas)
        ->firstWhere('nama', 'Kuitansi')['ada'];

    expect($ada($makan))->toBeTrue()
        ->and($ada($ringan))->toBeFalse();
});

test('gabung menolak beda bulan, jenis, sekolah, rapel, status manual, dan baris tanpa bulan', function (callable $ubah) {
    [$jenis] = jenisRapat();
    $sekolah = Sekolah::factory()->create();
    $a = barisRapat($sekolah, $jenis, 'A');
    $b = barisRapat($sekolah, $jenis, 'B');
    $ubah($b, $jenis);
    $sebelum = Acara::count();

    $this->post(route('acara.store'), ['kegiatan_ids' => [$a->id, $b->id], 'nama' => 'Rapat'])
        ->assertSessionHasErrors('kegiatan_ids');

    expect(Acara::count())->toBe($sebelum)
        ->and($a->fresh()->acara_id)->toBeNull();
})->with([
    'beda bulan' => [fn (Kegiatan $b) => $b->update(['bulan' => 4])],
    'beda jenis' => [fn (Kegiatan $b) => $b->update(['jenis_kegiatan_id' => JenisKegiatan::factory()->create()->id])],
    'beda sekolah' => [fn (Kegiatan $b) => $b->update(['sekolah_id' => Sekolah::factory()->create()->id])],
    'tanpa bulan' => [fn (Kegiatan $b) => $b->update(['bulan' => null])],
    'status manual' => [fn (Kegiatan $b) => $b->update(['status_manual' => 'di_luar_aplikasi'])],
    'hilang dari anggaran' => [fn (Kegiatan $b) => $b->update(['hilang_dari_anggaran' => true])],
    'sudah beracara' => [fn (Kegiatan $b) => $b->update(['acara_id' => Acara::factory()->create()->id])],
    'bagian rapel' => [function (Kegiatan $b) {
        $k = Kuitansi::factory()->create(['sekolah_id' => $b->sekolah_id, 'bulan' => 5]);
        $k->kegiatans()->attach($b->id);
    }],
]);

test('gabung menolak kurang dari dua baris', function () {
    [$jenis] = jenisRapat();
    $a = barisRapat(Sekolah::factory()->create(), $jenis, 'A');

    $this->post(route('acara.store'), ['kegiatan_ids' => [$a->id], 'nama' => 'Rapat'])
        ->assertSessionHasErrors('kegiatan_ids');
    $this->post(route('acara.store'), ['kegiatan_ids' => [$a->id, $a->id], 'nama' => 'Rapat'])
        ->assertSessionHasErrors();
    $this->post(route('acara.store'), ['kegiatan_ids' => [$a->id, 9999], 'nama' => ''])
        ->assertSessionHasErrors(['kegiatan_ids.1', 'nama']);
});

test('pisah mengembalikan semua foto ke baris pertama dan tidak ada foto hilang', function () {
    [$jenis, $undangan, $absen] = jenisRapat();
    $sekolah = Sekolah::factory()->create();
    $makan = barisRapat($sekolah, $jenis, 'Jamuan Makan');
    $ringan = barisRapat($sekolah, $jenis, 'Jamuan Ringan');
    fotoUntuk($makan, $undangan, 1);
    fotoUntuk($ringan, $undangan, 1);
    fotoUntuk($ringan, $absen, 1);
    $acara = app(GabungAcara::class)->gabung([$makan->id, $ringan->id], 'Rapat Maret');
    $paths = Foto::pluck('path')->all();

    $this->delete(route('acara.destroy', $acara))->assertSessionHasNoErrors();

    expect(Acara::count())->toBe(0)
        ->and($makan->fresh()->acara_id)->toBeNull()
        ->and($ringan->fresh()->acara_id)->toBeNull()
        ->and(Foto::count())->toBe(3)
        ->and(Foto::where('pemilik_type', Kegiatan::class)->where('pemilik_id', $makan->id)->count())->toBe(3)
        ->and(Foto::pluck('path')->sort()->values()->all())->toBe(collect($paths)->sort()->values()->all());

    foreach ($paths as $path) {
        Storage::disk('local')->assertExists($path);
    }
});

test('jenis kegiatan anggota acara tidak bisa diubah sebelum dipisah', function () {
    [$jenis] = jenisRapat();
    $sekolah = Sekolah::factory()->create();
    $a = barisRapat($sekolah, $jenis, 'A');
    $b = barisRapat($sekolah, $jenis, 'B');
    app(GabungAcara::class)->gabung([$a->id, $b->id], 'Rapat');
    $lain = JenisKegiatan::factory()->create();

    $this->patch(route('kegiatan.update', $a), ['jenis_kegiatan_id' => $lain->id])
        ->assertSessionHasErrors(['jenis_kegiatan_id' => 'Kegiatan ini bagian dari acara. Pisahkan acaranya dulu sebelum mengubah jenis.']);

    expect($a->fresh()->jenis_kegiatan_id)->toBe($jenis->id);
});

test('hapus anggota acara tidak menghapus foto bersama', function () {
    [$jenis, $undangan] = jenisRapat();
    $sekolah = Sekolah::factory()->create();
    $baris = [
        barisRapat($sekolah, $jenis, 'A'),
        barisRapat($sekolah, $jenis, 'B'),
        barisRapat($sekolah, $jenis, 'C'),
    ];
    fotoUntuk($baris[0], $undangan, 1);
    $acara = app(GabungAcara::class)->gabung(array_map(fn ($k) => $k->id, $baris), 'Rapat');
    $baris[2]->update(['hilang_dari_anggaran' => true]);

    $this->delete(route('kegiatan.destroy', $baris[2]))->assertSessionHasNoErrors();

    expect(Kegiatan::find($baris[2]->id))->toBeNull()
        ->and($acara->fresh())->not->toBeNull()
        ->and($acara->fotos()->count())->toBe(1);

    $baris[1]->update(['hilang_dari_anggaran' => true]);
    $this->delete(route('kegiatan.destroy', $baris[1]))->assertSessionHasNoErrors();

    expect(Acara::count())->toBe(0)
        ->and($baris[0]->fresh()->acara_id)->toBeNull()
        ->and(Foto::where('pemilik_type', Kegiatan::class)->where('pemilik_id', $baris[0]->id)->count())->toBe(1);
});

test('halaman kegiatan anggota acara memuat acaranya', function () {
    [$jenis] = jenisRapat();
    $sekolah = Sekolah::factory()->create();
    $a = barisRapat($sekolah, $jenis, 'A');
    $b = barisRapat($sekolah, $jenis, 'B');
    $acara = app(GabungAcara::class)->gabung([$a->id, $b->id], 'Rapat Maret');

    $this->get(route('kegiatan.show', $a))
        ->assertInertia(fn (Assert $page) => $page
            ->where('acara.id', $acara->id)
            ->where('acara.nama', 'Rapat Maret')
            ->where('acara.anggota.0.nama', 'A')
            ->where('acara.anggota.1.nama', 'B'));
});
