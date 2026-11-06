<?php

use App\Enums\SistemAnggaran;
use App\Enums\TataLetakBerkas;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\JenisBerkas;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use Database\Seeders\DataAwalSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->seed(DataAwalSeeder::class));

it('mengisi dua sekolah dengan penandatangan yang benar', function () {
    expect(Sekolah::count())->toBe(2);

    $smp = Sekolah::where('identitas', '20606714')->firstOrFail();
    expect($smp->sistem)->toBe(SistemAnggaran::Arkas)
        ->and($smp->kode_bukti)->toBe('SMPG')
        ->and($smp->nama_kepala)->toBe('Fahmie Al Khudhorie')
        ->and($smp->nama_bendahara)->toBe('Dra. Nurjanah')
        ->and($smp->nomor_awal)->toBe([]);

    $mi = Sekolah::where('identitas', '111236710070')->firstOrFail();
    expect($mi->sistem)->toBe(SistemAnggaran::Erkam)
        ->and($mi->kode_bukti)->toBe('MIDH')
        ->and($mi->nama_kepala)->toBe('Dra. Nurjanah')
        ->and($mi->nama_bendahara)->toBe('Fahmie Al Khudhorie, S.Pd');
});

it('mengisi jenis kegiatan awal beserta berkasnya', function () {
    expect(JenisKegiatan::orderBy('urutan')->pluck('nama')->all())
        ->toBe(['Langganan', 'Rapat/Pelatihan', 'Gaji/Honor', 'Belanja barang', 'Lainnya']);

    $langganan = JenisKegiatan::where('nama', 'Langganan')->firstOrFail();
    expect($langganan->kata_kunci)->toBe(['listrik', 'internet', 'bandwidth', 'retribusi', 'hosting'])
        ->and($langganan->jenisBerkas->pluck('nama')->all())->toBe(['Struk bayar']);

    $rapat = JenisKegiatan::where('nama', 'Rapat/Pelatihan')->firstOrFail();
    expect($rapat->jenisBerkas->pluck('nama')->all())
        ->toBe(['Undangan', 'Daftar hadir', 'Notulen', 'Dokumentasi foto'])
        ->and($rapat->jenisBerkas->last()->tata_letak)->toBe(TataLetakBerkas::Kisi);

    $gaji = JenisKegiatan::where('nama', 'Gaji/Honor')->firstOrFail();
    expect($gaji->jenisBerkas->pluck('nama')->all())->toBe(['Bukti penggajian'])
        ->and($gaji->jenisBerkas->first()->tata_letak)->toBe(TataLetakBerkas::Penuh);
    expect(JenisKegiatan::where('nama', 'Belanja barang')->firstOrFail()->jenisBerkas->pluck('nama')->all())
        ->toBe(['Nota', 'Foto barang']);

    $lainnya = JenisKegiatan::where('nama', 'Lainnya')->firstOrFail();
    expect($lainnya->cadangan)->toBeTrue()->and($lainnya->kata_kunci)->toBe([]);
});

it('aman dijalankan dua kali dan tidak menimpa ubahan pemilik', function () {
    Sekolah::where('kode_bukti', 'SMPG')->update(['nomor_awal' => ['2026' => 12], 'nama_kepala' => 'Kepala Baru']);
    JenisKegiatan::where('nama', 'Langganan')->update(['kata_kunci' => ['air']]);
    JenisBerkas::where('nama', 'Dokumentasi foto')->update(['tata_letak' => TataLetakBerkas::Penuh]);

    $this->seed(DataAwalSeeder::class);

    expect(Sekolah::count())->toBe(2)
        ->and(JenisKegiatan::count())->toBe(5)
        ->and(JenisKegiatan::where('nama', 'Rapat/Pelatihan')->firstOrFail()->jenisBerkas)->toHaveCount(4)
        ->and(Sekolah::where('kode_bukti', 'SMPG')->firstOrFail()->nomor_awal)->toBe(['2026' => 12])
        ->and(Sekolah::where('kode_bukti', 'SMPG')->firstOrFail()->nama_kepala)->toBe('Kepala Baru')
        ->and(JenisKegiatan::where('nama', 'Langganan')->firstOrFail()->kata_kunci)->toBe(['air'])
        ->and(JenisBerkas::where('nama', 'Dokumentasi foto')->firstOrFail()->tata_letak)->toBe(TataLetakBerkas::Penuh);
});

it('mengembalikan acara sebagai pemilik berkas kalau kegiatan punya acara', function () {
    $acara = Acara::factory()->create();
    $dalamAcara = Kegiatan::factory()->create(['acara_id' => $acara->id]);
    $sendiri = Kegiatan::factory()->create();

    expect($dalamAcara->pemilikBerkas())->toBeInstanceOf(Acara::class)
        ->and($dalamAcara->pemilikBerkas()->is($acara))->toBeTrue()
        ->and($sendiri->pemilikBerkas())->toBeInstanceOf(Kegiatan::class)
        ->and($sendiri->pemilikBerkas()->is($sendiri))->toBeTrue();
});

it('menghapus file saat foto dihapus', function () {
    Storage::fake('local');
    Storage::disk('local')->put('foto/a.jpg', 'isi');

    $foto = Foto::factory()->create(['path' => 'foto/a.jpg']);
    Storage::disk('local')->assertExists('foto/a.jpg');

    $foto->delete();

    Storage::disk('local')->assertMissing('foto/a.jpg');
});

it('menghubungkan kegiatan ke kuitansi lewat pivot', function () {
    $kegiatan = Kegiatan::factory()->create();
    $kuitansi = Kuitansi::factory()->create(['sekolah_id' => $kegiatan->sekolah_id]);

    $kegiatan->kuitansi()->attach($kuitansi);

    expect($kegiatan->kuitansi)->toHaveCount(1)
        ->and($kuitansi->kegiatans->first()->is($kegiatan))->toBeTrue();
});
