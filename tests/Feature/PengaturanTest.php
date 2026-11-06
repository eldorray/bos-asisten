<?php

use App\Enums\TataLetakBerkas;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Sekolah;
use App\Models\User;

function berkasKirim(JenisKegiatan $jenis): array
{
    return $jenis->jenisBerkas->map(fn (JenisBerkas $b) => [
        'id' => $b->id, 'nama' => $b->nama, 'tata_letak' => $b->tata_letak->value,
    ])->all();
}

function jenisDenganBerkas(string $nama = 'Honor'): JenisKegiatan
{
    $jenis = JenisKegiatan::factory()->create(['nama' => $nama, 'kata_kunci' => ['honor']]);
    JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Daftar hadir', 'urutan' => 1]);
    JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'SK', 'urutan' => 2, 'tata_letak' => TataLetakBerkas::Kisi]);

    return $jenis->load('jenisBerkas');
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('menolak tamu', function () {
    auth()->logout();
    $this->get(route('sekolah.index'))->assertRedirect('/login');
    $this->get(route('jenis-kegiatan.index'))->assertRedirect('/login');
});

it('menampilkan pengaturan sekolah', function () {
    Sekolah::factory()->create(['nomor_awal' => ['2026' => 5]]);

    $this->get(route('sekolah.index'))
        ->assertInertia(fn ($page) => $page->component('pengaturan/Sekolah')
            ->has('sekolahs', 1)
            ->where('sekolahs.0.nomor_awal.2026', 5)
            ->where('tahun', now()->year));
});

it('mengubah penandatangan dan nomor awal tahun terpilih tanpa menyentuh tahun lain', function () {
    $sekolah = Sekolah::factory()->create(['nomor_awal' => ['2025' => 7]]);

    $this->patch(route('sekolah.update', $sekolah), [
        'nama_kepala' => 'Budi Santoso', 'nama_bendahara' => 'Siti Aminah', 'tahun' => 2026, 'nomor_awal' => 12,
    ])->assertSessionHasNoErrors();

    $sekolah->refresh();
    expect($sekolah->nama_kepala)->toBe('Budi Santoso')
        ->and($sekolah->nama_bendahara)->toBe('Siti Aminah')
        ->and($sekolah->nomor_awal)->toBe([2025 => 7, 2026 => 12]);
});

it('tidak mengubah sistem dan identitas sekolah', function () {
    $sekolah = Sekolah::factory()->create(['identitas' => '12345678']);
    $sistem = $sekolah->sistem;

    $this->patch(route('sekolah.update', $sekolah), [
        'nama_kepala' => 'A', 'nama_bendahara' => 'B', 'sistem' => 'erkam', 'identitas' => '999',
    ])->assertSessionHasNoErrors();

    expect($sekolah->refresh()->identitas)->toBe('12345678')->and($sekolah->sistem)->toBe($sistem);
});

it('menolak nomor awal yang bukan bilangan bulat minimal 1', function (mixed $nilai) {
    $sekolah = Sekolah::factory()->create();

    $this->patch(route('sekolah.update', $sekolah), [
        'nama_kepala' => 'A', 'nama_bendahara' => 'B', 'tahun' => 2026, 'nomor_awal' => $nilai,
    ])->assertSessionHasErrors('nomor_awal');
})->with([0, -3, 'abc', 1.5]);

it('membatasi isian sekolah agar muat di kuitansi', function (string $kolom, int $batas, string $pesan) {
    $sekolah = Sekolah::factory()->create();
    $wajib = ['nama_kepala' => 'Kepala', 'nama_bendahara' => 'Bendahara'];

    $this->patch(route('sekolah.update', $sekolah), [$kolom => str_repeat('W', $batas)] + $wajib)
        ->assertSessionHasNoErrors();
    $this->patch(route('sekolah.update', $sekolah), [$kolom => str_repeat('W', $batas + 1)] + $wajib)
        ->assertSessionHasErrors([$kolom => $pesan]);

    expect($sekolah->refresh()->{$kolom})->toBe(str_repeat('W', $batas));
})->with([
    ['nama', 100, 'Nama sekolah maksimal 100 karakter agar muat di kuitansi.'],
    ['sebutan', 20, 'Sebutan maksimal 20 karakter agar muat di kuitansi.'],
    ['desa_kecamatan', 100, 'Desa/kecamatan maksimal 100 karakter agar muat di kuitansi.'],
    ['kabupaten', 100, 'Kabupaten maksimal 100 karakter agar muat di kuitansi.'],
    ['provinsi', 100, 'Provinsi maksimal 100 karakter agar muat di kuitansi.'],
    ['nama_kepala', 90, 'Nama kepala maksimal 90 karakter agar muat di kuitansi.'],
    ['nama_bendahara', 90, 'Nama bendahara maksimal 90 karakter agar muat di kuitansi.'],
]);

it('menambah jenis kegiatan dengan berkas berurutan', function () {
    $this->post(route('jenis-kegiatan.store'), [
        'nama' => 'Perjalanan dinas',
        'kata_kunci' => 'dinas, perjalanan , ,dinas',
        'berkas' => [
            ['nama' => 'Surat tugas', 'tata_letak' => 'penuh'],
            ['nama' => 'Tiket', 'tata_letak' => 'kisi'],
        ],
    ])->assertSessionHasNoErrors();

    $jenis = JenisKegiatan::where('nama', 'Perjalanan dinas')->firstOrFail();
    expect($jenis->kata_kunci)->toBe(['dinas', 'perjalanan'])
        ->and($jenis->cadangan)->toBeFalse()
        ->and($jenis->jenisBerkas->pluck('nama')->all())->toBe(['Surat tugas', 'Tiket'])
        ->and($jenis->jenisBerkas->pluck('urutan')->all())->toBe([1, 2])
        ->and($jenis->jenisBerkas[1]->tata_letak)->toBe(TataLetakBerkas::Kisi);
});

it('menyimpan urutan berkas baru, nama baru, berkas baru, dan menghapus yang tidak dikirim', function () {
    $jenis = jenisDenganBerkas();
    [$hadir, $sk] = $jenis->jenisBerkas->all();

    $this->put(route('jenis-kegiatan.update', $jenis), [
        'nama' => 'Honor kegiatan',
        'kata_kunci' => ['honor', 'upah'],
        'berkas' => [
            ['id' => $sk->id, 'nama' => 'SK', 'tata_letak' => 'penuh'],
            ['id' => '', 'nama' => 'Pajak', 'tata_letak' => 'kisi'],
        ],
    ])->assertSessionHasNoErrors();

    $jenis->refresh()->load('jenisBerkas');
    expect($jenis->nama)->toBe('Honor kegiatan')
        ->and($jenis->kata_kunci)->toBe(['honor', 'upah'])
        ->and($jenis->jenisBerkas->pluck('nama')->all())->toBe(['SK', 'Pajak'])
        ->and($jenis->jenisBerkas[0]->id)->toBe($sk->id)
        ->and($jenis->jenisBerkas[0]->tata_letak)->toBe(TataLetakBerkas::Penuh)
        ->and(JenisBerkas::find($hadir->id))->toBeNull();
});

it('mengubah urutan berkas', function () {
    $jenis = jenisDenganBerkas();

    $this->put(route('jenis-kegiatan.update', $jenis), [
        'nama' => $jenis->nama, 'kata_kunci' => 'honor',
        'berkas' => array_reverse(berkasKirim($jenis)),
    ])->assertSessionHasNoErrors();

    expect($jenis->refresh()->jenisBerkas->pluck('nama')->all())->toBe(['SK', 'Daftar hadir']);
});

it('menolak menghapus berkas yang sudah punya foto dengan pesan', function () {
    $jenis = jenisDenganBerkas();
    [$hadir, $sk] = $jenis->jenisBerkas->all();
    Foto::factory()->create(['jenis_berkas_id' => $hadir->id]);

    $this->from(route('jenis-kegiatan.index'))->put(route('jenis-kegiatan.update', $jenis), [
        'nama' => 'Baru', 'kata_kunci' => 'honor',
        'berkas' => [['id' => $sk->id, 'nama' => 'SK', 'tata_letak' => 'penuh']],
    ])->assertSessionHasErrors('berkas')->assertRedirect(route('jenis-kegiatan.index'));

    expect(session('errors')->first('berkas'))->toContain('Daftar hadir');

    expect(JenisBerkas::find($hadir->id))->not->toBeNull()->and($jenis->refresh()->nama)->toBe('Honor');
});

it('menolak id berkas milik jenis lain', function () {
    $jenis = jenisDenganBerkas('Honor');
    $lain = jenisDenganBerkas('Belanja');
    $asing = $lain->jenisBerkas->first();

    $this->put(route('jenis-kegiatan.update', $jenis), [
        'nama' => 'Honor', 'kata_kunci' => 'honor',
        'berkas' => [['id' => $asing->id, 'nama' => 'Curian', 'tata_letak' => 'penuh']],
    ])->assertSessionHasErrors('berkas.0.id');

    expect($asing->refresh()->jenis_kegiatan_id)->toBe($lain->id);
});

it('menolak nama berkas ganda, nama kuitansi, dan tata letak asing', function () {
    $jenis = jenisDenganBerkas();

    $this->put(route('jenis-kegiatan.update', $jenis), [
        'nama' => 'Honor', 'kata_kunci' => '',
        'berkas' => [
            ['nama' => 'A', 'tata_letak' => 'penuh'],
            ['nama' => 'a', 'tata_letak' => 'penuh'],
            ['nama' => 'Kuitansi', 'tata_letak' => 'penuh'],
            ['nama' => 'B', 'tata_letak' => 'miring'],
        ],
    ])->assertSessionHasErrors(['berkas.0.nama', 'berkas.2.nama', 'berkas.3.tata_letak']);
});

it('menolak nama jenis kegiatan yang sudah ada', function () {
    jenisDenganBerkas('Honor');

    $this->post(route('jenis-kegiatan.store'), ['nama' => 'Honor', 'kata_kunci' => '', 'berkas' => []])
        ->assertSessionHasErrors('nama');
});

it('menampilkan daftar jenis kegiatan', function () {
    jenisDenganBerkas();

    $this->get(route('jenis-kegiatan.index'))
        ->assertInertia(fn ($page) => $page->component('pengaturan/JenisKegiatan')
            ->has('jenisKegiatans', 1)
            ->where('jenisKegiatans.0.kata_kunci', 'honor')
            ->has('jenisKegiatans.0.berkas', 2)
            ->where('jenisKegiatans.0.berkas.0.nama', 'Daftar hadir'));
});

it('menghapus jenis kegiatan yang tidak dipakai beserta berkasnya', function () {
    $jenis = jenisDenganBerkas();

    $this->delete(route('jenis-kegiatan.destroy', $jenis))->assertSessionHasNoErrors();

    expect(JenisKegiatan::find($jenis->id))->toBeNull()->and(JenisBerkas::count())->toBe(0);
});

it('menolak menghapus jenis kegiatan yang dipakai kegiatan atau acara', function () {
    $dipakai = jenisDenganBerkas('Dipakai');
    Kegiatan::factory()->create(['jenis_kegiatan_id' => $dipakai->id]);
    $beracara = jenisDenganBerkas('Beracara');
    Acara::factory()->create(['jenis_kegiatan_id' => $beracara->id]);

    $this->delete(route('jenis-kegiatan.destroy', $dipakai))->assertSessionHasErrors('hapus');
    $this->delete(route('jenis-kegiatan.destroy', $beracara))->assertSessionHasErrors('hapus');

    expect(JenisKegiatan::find($dipakai->id))->not->toBeNull()->and(JenisKegiatan::find($beracara->id))->not->toBeNull();
});

it('menolak menghapus jenis kegiatan cadangan dan jenis yang berkasnya punya foto', function () {
    $cadangan = JenisKegiatan::factory()->create(['cadangan' => true]);
    $berfoto = jenisDenganBerkas('Berfoto');
    Foto::factory()->create(['jenis_berkas_id' => $berfoto->jenisBerkas->first()->id]);

    $this->delete(route('jenis-kegiatan.destroy', $cadangan))->assertSessionHasErrors('hapus');
    $this->delete(route('jenis-kegiatan.destroy', $berfoto))->assertSessionHasErrors('hapus');

    expect(JenisKegiatan::find($cadangan->id))->not->toBeNull()->and(JenisKegiatan::find($berfoto->id))->not->toBeNull();
});

it('tidak mengubah flag cadangan lewat update', function () {
    $jenis = JenisKegiatan::factory()->create(['cadangan' => true, 'nama' => 'Lain-lain']);

    $this->put(route('jenis-kegiatan.update', $jenis), [
        'nama' => 'Lain-lain', 'kata_kunci' => '', 'cadangan' => false, 'berkas' => [],
    ])->assertSessionHasNoErrors();

    expect($jenis->refresh()->cadangan)->toBeTrue();
});

it('menukar nama dua berkas dalam satu simpanan', function () {
    $jenis = jenisDenganBerkas();
    [$hadir, $sk] = $jenis->jenisBerkas->all();

    $this->put(route('jenis-kegiatan.update', $jenis), [
        'nama' => 'Honor', 'kata_kunci' => 'honor',
        'berkas' => [
            ['id' => $hadir->id, 'nama' => 'SK', 'tata_letak' => 'penuh'],
            ['id' => $sk->id, 'nama' => 'Daftar hadir', 'tata_letak' => 'penuh'],
        ],
    ])->assertSessionHasNoErrors();

    expect($hadir->refresh()->nama)->toBe('SK')->and($sk->refresh()->nama)->toBe('Daftar hadir');
});

it('menggeser nama berkas melintasi tiga baris dalam satu simpanan', function () {
    $jenis = jenisDenganBerkas();
    $ketiga = JenisBerkas::factory()->create(['jenis_kegiatan_id' => $jenis->id, 'nama' => 'Foto', 'urutan' => 3]);
    [$a, $b] = $jenis->jenisBerkas->all();

    $this->put(route('jenis-kegiatan.update', $jenis), [
        'nama' => 'Honor', 'kata_kunci' => 'honor',
        'berkas' => [
            ['id' => $a->id, 'nama' => 'SK', 'tata_letak' => 'penuh'],
            ['id' => $b->id, 'nama' => 'Foto', 'tata_letak' => 'penuh'],
            ['id' => $ketiga->id, 'nama' => 'Baru', 'tata_letak' => 'penuh'],
        ],
    ])->assertSessionHasNoErrors();

    expect($jenis->refresh()->jenisBerkas->pluck('nama')->all())->toBe(['SK', 'Foto', 'Baru']);
});
