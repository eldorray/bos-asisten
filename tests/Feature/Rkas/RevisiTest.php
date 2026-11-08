<?php

declare(strict_types=1);

use App\Actions\Rkas\BuatRevisi;
use App\Actions\Rkas\HitungRingkasan;
use App\Actions\Rkas\PesanRkas;
use App\Actions\Rkas\UbahStatusKertasKerja;
use App\Enums\JenisVersiRkas;
use App\Enums\SistemAnggaran;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use App\Models\Sekolah;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DataAwalSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();
    $this->actingAs(User::factory()->create());

    $this->kk = fn (array $lain = []): RkasKertasKerja => RkasKertasKerja::factory()->create(array_replace([
        'sekolah_id' => $this->smp->id, 'tahun' => 2027, 'pagu' => 100_000_000, 'status' => StatusKertasKerja::Disahkan,
    ], $lain));

    $this->baris = function (RkasKertasKerja $kk, array $volume, int $harga, array $lain = [], string $kodeRekening = '5.1.02.01.01.0001'): RkasBaris {
        $kegiatan = RkasRefKegiatan::firstOrCreate(['tahun' => 2027, 'kode' => '07.01.01.'], ['uraian' => 'K', 'level' => 3]);
        $rekening = RkasRefRekening::firstOrCreate(['tahun' => 2027, 'kode' => $kodeRekening], ['nama' => 'R']);

        return RkasBaris::factory()->create(array_replace([
            'kertas_kerja_id' => $kk->id,
            'ref_kegiatan_id' => $kegiatan->id,
            'ref_rekening_id' => $rekening->id,
            'harga' => $harga,
            'volume_bulan' => array_replace(array_fill_keys(range(1, 12), 0), $volume),
        ], $lain));
    };

    $this->tanggal = CarbonImmutable::parse('2027-07-15');
});

it('membuat pergeseran yang menyalin semua baris dan menjaga versi lama', function () {
    $kk = ($this->kk)();
    $kk->pemberiDanas()->create(['nama' => 'Donatur', 'jumlah' => 5]);
    $a = ($this->baris)($kk, [1 => 2], 50_000_000);
    $b = ($this->baris)($kk, [9 => 1], 1_000_000, ['urutan' => 2]);

    $this->post(route('rkas.revisi.store', $kk), ['jenis' => 'pergeseran', 'tanggal_revisi' => '2027-07-15'])
        ->assertRedirect();

    $baru = RkasKertasKerja::where('induk_id', $kk->id)->firstOrFail();
    expect($baru->jenis_versi)->toBe(JenisVersiRkas::Pergeseran)
        ->and($baru->nomor_pergeseran)->toBe(1)
        ->and($baru->status)->toBe(StatusKertasKerja::Draf)
        ->and($baru->tanggal_revisi->toDateString())->toBe('2027-07-15')
        ->and($baru->pagu)->toBe(100_000_000)
        ->and($baru->pemberiDanas()->pluck('nama')->all())->toBe(['Donatur'])
        ->and($baru->baris()->pluck('asal_baris_id')->sort()->values()->all())->toBe([$a->id, $b->id])
        ->and($baru->baris()->orderBy('id')->first()->volume_bulan)->toEqual($a->volume_bulan);

    expect($kk->fresh()->status)->toBe(StatusKertasKerja::Disahkan)
        ->and($kk->baris()->count())->toBe(2);
});

it('membuat perubahan dengan pagu baru dan mereset nomor pergeseran', function () {
    $kk = ($this->kk)(['jenis_versi' => JenisVersiRkas::Pergeseran, 'nomor_pergeseran' => 2]);
    ($this->baris)($kk, [1 => 1], 1_000);

    $baru = app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Perubahan, $this->tanggal, 120_000_000);

    expect($baru->jenis_versi)->toBe(JenisVersiRkas::Perubahan)
        ->and($baru->nomor_perubahan)->toBe(1)
        ->and($baru->nomor_pergeseran)->toBe(0)
        ->and($baru->pagu)->toBe(120_000_000)
        ->and($baru->induk_id)->toBe($kk->id)
        ->and($baru->labelVersi())->toBe('Perubahan 1');

    $this->post(route('rkas.revisi.store', $kk), ['jenis' => 'perubahan', 'tanggal_revisi' => '2027-07-15'])
        ->assertSessionHasErrors('pagu_baru');
});

it('menjaga pagu perubahan sumber dana Lainnya sama dengan jumlah pemberi dana', function () {
    $kk = ($this->kk)(['sumber_dana' => SumberDanaRkas::Lainnya, 'pagu' => 30]);
    $kk->pemberiDanas()->create(['nama' => 'A', 'jumlah' => 10]);
    $kk->pemberiDanas()->create(['nama' => 'B', 'jumlah' => 20]);

    $this->post(route('rkas.revisi.store', $kk), ['jenis' => 'perubahan', 'tanggal_revisi' => '2027-07-15', 'pagu_baru' => 999])
        ->assertSessionHasNoErrors();

    $baru = RkasKertasKerja::where('induk_id', $kk->id)->firstOrFail();
    expect($baru->pagu)->toBe(30)
        ->and($baru->pagu)->toBe((int) $baru->pemberiDanas()->sum('jumlah'));
});

it('menolak revisi dari versi yang belum disahkan', function () {
    $kk = ($this->kk)(['status' => StatusKertasKerja::Siap]);

    $this->post(route('rkas.revisi.store', $kk), ['jenis' => 'pergeseran', 'tanggal_revisi' => '2027-07-15'])
        ->assertSessionHasErrors(['kertas_kerja' => sprintf(PesanRkas::TERKUNCI, 'Siap')]);

    expect(RkasKertasKerja::where('induk_id', $kk->id)->exists())->toBeFalse();
});

it('menolak revisi kedua saat revisi sebelumnya masih draf', function () {
    $kk = ($this->kk)();
    app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Pergeseran, $this->tanggal);

    $this->post(route('rkas.revisi.store', $kk), ['jenis' => 'pergeseran', 'tanggal_revisi' => '2027-07-15'])
        ->assertSessionHasErrors(['kertas_kerja' => PesanRkas::REVISI_TERBUKA]);

    expect(RkasKertasKerja::where('induk_id', $kk->id)->count())->toBe(1);
});

it('menolak revisi dari versi lama saat ada versi lain di rantai yang belum disahkan', function () {
    $awal = ($this->kk)();
    $p1 = app(BuatRevisi::class)->jalankan($awal, JenisVersiRkas::Pergeseran, $this->tanggal);
    $p1->update(['status' => StatusKertasKerja::Disahkan]);
    app(BuatRevisi::class)->jalankan($p1, JenisVersiRkas::Pergeseran, $this->tanggal);

    expect(fn () => app(BuatRevisi::class)->jalankan($awal, JenisVersiRkas::Perubahan, $this->tanggal, 1))
        ->toThrow(ValidationException::class, PesanRkas::REVISI_TERBUKA);
});

it('menolak pergeseran melebihi kuota', function () {
    RkasPengaturan::untuk($this->smp, 2027)->update(['kuota_pergeseran' => 1]);
    $kk = ($this->kk)();
    $p1 = app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Pergeseran, $this->tanggal);
    $p1->update(['status' => StatusKertasKerja::Disahkan]);

    $this->post(route('rkas.revisi.store', $p1), ['jenis' => 'pergeseran', 'tanggal_revisi' => '2027-07-15'])
        ->assertSessionHasErrors(['kertas_kerja' => PesanRkas::KUOTA_PERGESERAN]);

    RkasPengaturan::untuk($this->smp, 2027)->update(['kuota_perubahan' => 0]);
    $this->post(route('rkas.revisi.store', $p1), ['jenis' => 'perubahan', 'tanggal_revisi' => '2027-07-15', 'pagu_baru' => 1])
        ->assertSessionHasErrors(['kertas_kerja' => PesanRkas::KUOTA_PERUBAHAN]);
});

it('menolak siap untuk pergeseran lintas jenis belanja', function () {
    $kk = ($this->kk)();
    ($this->baris)($kk, [1 => 1], 99_000_000);
    ($this->baris)($kk, [1 => 1], 1_000_000, ['urutan' => 2], '5.1.02.01.01.0002');

    $p = app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Pergeseran, $this->tanggal);
    $pindah = $p->baris()->where('harga', 1_000_000)->firstOrFail();
    $alat = RkasRefRekening::firstOrCreate(['tahun' => 2027, 'kode' => '5.2.02.01.01.0001'], ['nama' => 'Alat']);
    $pindah->update(['ref_rekening_id' => $alat->id]);

    $hasil = app(UbahStatusKertasKerja::class)->nyatakanSiap($p->fresh());

    expect($hasil->pemblokir)->toContain(sprintf(PesanRkas::LINTAS_JENIS, 'Operasional, Alat dan Mesin'))
        ->and($p->fresh()->status)->toBe(StatusKertasKerja::Draf);
});

it('memperingatkan perubahan volume di bulan sebelum tanggal revisi', function () {
    $kk = ($this->kk)();
    ($this->baris)($kk, [3 => 1, 9 => 1], 50_000_000);

    $p = app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Pergeseran, $this->tanggal);
    $baris = $p->baris()->firstOrFail();

    $ringkasan = app(HitungRingkasan::class)->untuk($p->fresh());
    expect($ringkasan->masalahPerBaris)->toBe([])
        ->and($ringkasan->jumlahBarisBermasalah)->toBe(0);

    $baris->update(['volume_bulan' => array_replace($baris->volume_bulan, [3 => 2, 9 => 0])]);
    ($this->baris)($p, [2 => 1], 0, ['urutan' => 2, 'uraian' => 'Baru']);

    $ringkasan = app(HitungRingkasan::class)->untuk($p->fresh());
    expect($ringkasan->masalahPerBaris[$baris->id])->toBe([PesanRkas::BULAN_LEWAT])
        ->and($ringkasan->masalahPerBaris)->toHaveCount(2)
        ->and($ringkasan->jumlahBarisBermasalah)->toBe(0);

    // Peringatan tidak memblokir: kertas kerja tetap bisa dinyatakan siap.
    expect(app(UbahStatusKertasKerja::class)->nyatakanSiap($p->fresh())->lolos())->toBeTrue()
        ->and($p->fresh()->status)->toBe(StatusKertasKerja::Siap);

    // Hanya bulan setelah tanggal revisi yang berubah: tidak ada peringatan.
    $baris->update(['volume_bulan' => array_replace($baris->volume_bulan, [3 => 1, 9 => 3])]);
    $p->baris()->where('uraian', 'Baru')->delete();
    expect(app(HitungRingkasan::class)->untuk($p->fresh())->masalahPerBaris)->toBe([]);
});

it('mengembalikan 404 untuk sekolah non-ARKAS dan menolak tamu', function () {
    $kk = ($this->kk)();
    $this->smp->update(['sistem' => SistemAnggaran::Erkam]);

    $this->post(route('rkas.revisi.store', $kk), ['jenis' => 'pergeseran', 'tanggal_revisi' => '2027-07-15'])->assertNotFound();

    auth()->logout();
    $this->post(route('rkas.revisi.store', $kk), [])->assertRedirect(route('login'));
});

it('mengirim daftar versi dan hak revisi hanya untuk versi disahkan terakhir', function () {
    $kk = ($this->kk)();
    $baru = app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Pergeseran, $this->tanggal);

    $this->get(route('rkas.show', $kk))->assertInertia(fn ($page) => $page
        ->where('kertasKerja.bisa_direvisi', false)
        ->has('rantai', 2)
        ->where('rantai.1.versi', 'Pergeseran 1'));

    $baru->update(['status' => StatusKertasKerja::Disahkan]);

    $this->get(route('rkas.show', $baru))->assertInertia(fn ($page) => $page->where('kertasKerja.bisa_direvisi', true));
    $this->get(route('rkas.show', $kk))->assertInertia(fn ($page) => $page->where('kertasKerja.bisa_direvisi', false));
});

it('tidak menerapkan blokir lintas jenis pada sumber dana Lainnya', function () {
    $kk = ($this->kk)(['sumber_dana' => SumberDanaRkas::Lainnya, 'pagu' => 100_000_000]);
    ($this->baris)($kk, [1 => 1], 99_000_000);
    ($this->baris)($kk, [1 => 1], 1_000_000, ['urutan' => 2], '5.1.02.01.01.0002');

    $p = app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Pergeseran, $this->tanggal);
    $alat = RkasRefRekening::firstOrCreate(['tahun' => 2027, 'kode' => '5.2.02.01.01.0001'], ['nama' => 'Alat']);
    $p->baris()->where('harga', 1_000_000)->firstOrFail()->update(['ref_rekening_id' => $alat->id]);

    $hasil = app(UbahStatusKertasKerja::class)->nyatakanSiap($p->fresh());

    expect($hasil->pemblokir)->toBe([])
        ->and($p->fresh()->status)->toBe(StatusKertasKerja::Siap);
});

it('memperingatkan baris bervolume bulan lewat yang dihapus pada revisi', function () {
    $kk = ($this->kk)();
    ($this->baris)($kk, [3 => 1], 50_000_000);
    ($this->baris)($kk, [3 => 1], 10_000_000, ['urutan' => 2]);
    ($this->baris)($kk, [9 => 1], 1_000_000, ['urutan' => 3]);

    $p = app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Pergeseran, $this->tanggal);
    expect(app(HitungRingkasan::class)->untuk($p->fresh())->peringatan)->not->toContain(sprintf(PesanRkas::BARIS_LEWAT_DIHAPUS, 1));

    // Baris bervolume Maret dihapus: peringatan. Baris yang hanya punya volume September dihapus: tidak dihitung.
    $p->baris()->where('harga', 10_000_000)->delete();
    $p->baris()->where('harga', 1_000_000)->delete();

    $ringkasan = app(HitungRingkasan::class)->untuk($p->fresh());
    expect($ringkasan->peringatan)->toContain(sprintf(PesanRkas::BARIS_LEWAT_DIHAPUS, 1))
        ->and($ringkasan->jumlahBarisBermasalah)->toBe(0);
});

it('menandai perubahan harga pada baris salinan yang punya volume bulan lewat', function () {
    $kk = ($this->kk)();
    ($this->baris)($kk, [3 => 1], 50_000_000);
    ($this->baris)($kk, [9 => 1], 1_000_000, ['urutan' => 2]);

    $p = app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Pergeseran, $this->tanggal);
    $lewat = $p->baris()->where('harga', 50_000_000)->firstOrFail();
    $nanti = $p->baris()->where('harga', 1_000_000)->firstOrFail();

    $lewat->update(['harga' => 40_000_000]);
    $nanti->update(['harga' => 2_000_000]);

    $ringkasan = app(HitungRingkasan::class)->untuk($p->fresh());
    expect($ringkasan->masalahPerBaris)->toHaveKey($lewat->id)
        ->and($ringkasan->masalahPerBaris[$lewat->id])->toBe([PesanRkas::BULAN_LEWAT])
        ->and($ringkasan->masalahPerBaris)->not->toHaveKey($nanti->id);
});

it('mengirim peringatan bulan lewat terpisah dari masalah referensi di props baris', function () {
    $kk = ($this->kk)();
    ($this->baris)($kk, [3 => 1], 50_000_000);

    $p = app(BuatRevisi::class)->jalankan($kk, JenisVersiRkas::Pergeseran, $this->tanggal);
    $p->baris()->firstOrFail()->update(['harga' => 40_000_000]);

    $this->get(route('rkas.show', $p))
        ->assertInertia(fn (Assert $page) => $page
            ->where('baris.0.masalah', [])
            ->where('baris.0.peringatan', [PesanRkas::BULAN_LEWAT]));
});
