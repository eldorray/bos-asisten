<?php

declare(strict_types=1);

use App\Actions\Rkas\PesanRkas;
use App\Actions\Rkas\SalinReferensi;
use App\Models\RkasBaris;
use App\Models\RkasPtk;
use App\Models\RkasRefBarang;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use App\Models\RkasRefSatuan;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();
    $this->actingAs(User::factory()->create());
});

it('menampilkan referensi per jenis dengan paginasi 50 per halaman', function () {
    RkasRefRekening::factory()->count(55)->create(['tahun' => 2027]);

    $this->get(route('rkas.referensi.index', ['tahun' => 2027, 'jenis' => 'rekening']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pengaturan/ReferensiRkas')
            ->where('jenis', 'rekening')
            ->where('tahun', 2027)
            ->has('daftar.data', 50)
            ->where('daftar.total', 55)
            ->missing('daftar.data.0.created_at'));
});

it('menambah kegiatan level 3 di bawah komponen yang ada', function () {
    RkasRefKegiatan::factory()->create(['tahun' => 2027, 'kode' => '07.12.', 'level' => 2, 'kode_induk' => '07.']);

    $this->post(route('rkas.referensi.store', 'kegiatan'), [
        'tahun' => 2027,
        'kode' => '07.12.05.',
        'kode_induk' => '07.12.',
        'uraian' => 'Honor tambahan',
    ])->assertSessionHasNoErrors();

    $kegiatan = RkasRefKegiatan::where('kode', '07.12.05.')->firstOrFail();
    expect($kegiatan->level)->toBe(3)
        ->and($kegiatan->kode_induk)->toBe('07.12.')
        ->and($kegiatan->sumber_dana)->toBe('bosp_reguler');
});

it('menolak kegiatan level 3 tanpa induk', function () {
    $this->post(route('rkas.referensi.store', 'kegiatan'), [
        'tahun' => 2027,
        'kode' => '07.12.05.',
        'kode_induk' => '07.12.',
        'uraian' => 'Honor tambahan',
    ])->assertSessionHasErrors('kode_induk');

    $this->post(route('rkas.referensi.store', 'kegiatan'), [
        'tahun' => 2027,
        'kode' => '07.12.05.',
        'uraian' => 'Honor tambahan',
    ])->assertSessionHasErrors('kode_induk');

    expect(RkasRefKegiatan::count())->toBe(0);
});

it('menolak kode kegiatan dan rekening dengan format salah', function () {
    $this->post(route('rkas.referensi.store', 'kegiatan'), ['tahun' => 2027, 'kode' => '07.1', 'uraian' => 'x'])
        ->assertSessionHasErrors('kode');
    $this->post(route('rkas.referensi.store', 'rekening'), ['tahun' => 2027, 'kode' => '5.1.02', 'nama' => 'x'])
        ->assertSessionHasErrors('kode');
});

it('menambah rekening dan barang acuan dengan batas harga', function () {
    $this->post(route('rkas.referensi.store', 'rekening'), [
        'tahun' => 2027, 'kode' => '5.1.02.02.01.0013', 'nama' => 'Honorarium',
    ])->assertSessionHasNoErrors();

    $this->post(route('rkas.referensi.store', 'barang'), [
        'tahun' => 2027, 'nama' => 'Kertas HVS', 'satuan' => 'Rim', 'harga' => 50000, 'batas_bawah' => 40000, 'batas_atas' => 60000,
    ])->assertSessionHasNoErrors();

    $this->post(route('rkas.referensi.store', 'barang'), [
        'tahun' => 2027, 'nama' => 'Kertas F4', 'satuan' => 'Rim', 'harga' => 70000, 'batas_bawah' => 40000, 'batas_atas' => 60000,
    ])->assertSessionHasErrors('harga');

    $this->post(route('rkas.referensi.store', 'barang'), [
        'tahun' => 2027, 'nama' => 'Kertas A4', 'satuan' => 'Rim', 'harga' => 30000, 'batas_bawah' => 40000, 'batas_atas' => 60000,
    ])->assertSessionHasErrors('harga');

    expect(RkasRefRekening::count())->toBe(1)->and(RkasRefBarang::count())->toBe(1);
});

it('menolak satuan ganda beda huruf', function () {
    $this->post(route('rkas.referensi.store', 'satuan'), ['nama' => 'Paket'])->assertSessionHasNoErrors();
    $this->post(route('rkas.referensi.store', 'satuan'), ['nama' => 'paket'])->assertSessionHasErrors('nama');

    expect(RkasRefSatuan::count())->toBe(1);
});

it('menambah ptk untuk sekolah ARKAS saja', function () {
    $this->post(route('rkas.referensi.store', 'ptk'), [
        'sekolah_id' => $this->smp->id, 'nama' => 'Budi', 'nuptk' => '1234567890123456', 'jenis' => 1,
    ])->assertSessionHasNoErrors();

    $this->post(route('rkas.referensi.store', 'ptk'), [
        'sekolah_id' => $this->smp->id, 'nama' => 'Ani', 'nuptk' => '123', 'jenis' => 1,
    ])->assertSessionHasErrors('nuptk');

    $this->post(route('rkas.referensi.store', 'ptk'), [
        'sekolah_id' => $this->smp->id, 'nama' => 'Ani', 'jenis' => 9,
    ])->assertSessionHasErrors('jenis');

    $mi = Sekolah::where('id', '!=', $this->smp->id)->firstOrFail();
    $this->post(route('rkas.referensi.store', 'ptk'), [
        'sekolah_id' => $mi->id, 'nama' => 'Cici', 'jenis' => 1,
    ])->assertSessionHasErrors('sekolah_id');

    expect(RkasPtk::pluck('nama')->all())->toBe(['Budi']);
});

it('menyalin referensi 2026 ke 2027 tanpa mengubah 2026', function () {
    RkasRefKegiatan::factory()->create(['tahun' => 2026, 'kode' => '07.', 'level' => 1]);
    RkasRefKegiatan::factory()->create(['tahun' => 2026, 'kode' => '07.12.', 'level' => 2, 'kode_induk' => '07.']);
    RkasRefKegiatan::factory()->create(['tahun' => 2026, 'kode' => '07.99.99.', 'kedaluwarsa' => now()->toDateString()]);
    RkasRefRekening::factory()->create(['tahun' => 2026, 'kode' => '5.1.02.02.01.0013']);
    RkasRefRekening::factory()->create(['tahun' => 2027, 'kode' => '5.1.02.02.01.0013']);
    RkasRefBarang::factory()->create(['tahun' => 2026, 'nama' => 'Kertas HVS', 'kode_rekening' => '5.2.02.01.01.0001']);
    RkasRefBarang::factory()->create(['tahun' => 2026, 'nama' => 'Lama', 'kedaluwarsa' => now()->subDay()]);

    $this->post(route('rkas.referensi.salin'), ['dari' => 2026, 'ke' => 2027])->assertSessionHasNoErrors();

    expect(RkasRefKegiatan::where('tahun', 2027)->pluck('kode')->sort()->values()->all())->toBe(['07.', '07.12.'])
        ->and(RkasRefRekening::where('tahun', 2027)->count())->toBe(1)
        ->and(RkasRefBarang::where('tahun', 2027)->pluck('nama')->all())->toBe(['Kertas HVS'])
        ->and(RkasRefKegiatan::where('tahun', 2026)->count())->toBe(3)
        ->and(RkasRefRekening::where('tahun', 2026)->count())->toBe(1)
        ->and(RkasRefBarang::where('tahun', 2026)->count())->toBe(2);

    // Salin kedua kali tidak menggandakan apa pun.
    $hasil = app(SalinReferensi::class)->jalankan(2026, 2027);
    expect($hasil)->toBe(['kegiatan' => 0, 'rekening' => 0, 'barang' => 0]);
});

it('mengembalikan jumlah yang disalin dari action', function () {
    RkasRefKegiatan::factory()->count(2)->create(['tahun' => 2026]);
    RkasRefRekening::factory()->create(['tahun' => 2026, 'kode' => '5.1.02.02.01.0013']);
    RkasRefRekening::factory()->create(['tahun' => 2027, 'kode' => '5.1.02.02.01.0013']);
    RkasRefBarang::factory()->create(['tahun' => 2026]);

    expect(app(SalinReferensi::class)->jalankan(2026, 2027))
        ->toBe(['kegiatan' => 2, 'rekening' => 0, 'barang' => 1]);
});

it('menolak menyalin ke tahun yang sama', function () {
    $this->post(route('rkas.referensi.salin'), ['dari' => 2027, 'ke' => 2027])->assertSessionHasErrors('ke');
});

it('menolak menghapus rekening yang dipakai baris', function () {
    $dipakai = RkasRefRekening::factory()->create(['tahun' => 2027]);
    $bebas = RkasRefRekening::factory()->create(['tahun' => 2027]);
    RkasBaris::factory()->create(['ref_rekening_id' => $dipakai->id]);

    $this->delete(route('rkas.referensi.destroy', ['jenis' => 'rekening', 'uuid' => $dipakai->uuid]))
        ->assertSessionHasErrors(['hapus' => sprintf(PesanRkas::REFERENSI_DIPAKAI, 1)]);
    $this->delete(route('rkas.referensi.destroy', ['jenis' => 'rekening', 'uuid' => $bebas->uuid]))
        ->assertSessionHasNoErrors();

    expect(RkasRefRekening::whereKey($dipakai->id)->exists())->toBeTrue()
        ->and(RkasRefRekening::whereKey($bebas->id)->exists())->toBeFalse();
});

it('menolak menghapus kegiatan, barang, dan ptk yang dipakai baris', function () {
    $kegiatan = RkasRefKegiatan::factory()->create(['tahun' => 2027]);
    $barang = RkasRefBarang::factory()->create(['tahun' => 2027]);
    $ptk = RkasPtk::factory()->create(['sekolah_id' => $this->smp->id]);
    RkasBaris::factory()->create(['ref_kegiatan_id' => $kegiatan->id, 'ref_barang_id' => $barang->id, 'ptk_id' => $ptk->id]);

    foreach ([['kegiatan', $kegiatan], ['barang', $barang], ['ptk', $ptk]] as [$jenis, $ref]) {
        $this->delete(route('rkas.referensi.destroy', ['jenis' => $jenis, 'uuid' => $ref->uuid]))
            ->assertSessionHasErrors(['hapus' => sprintf(PesanRkas::REFERENSI_DIPAKAI, 1)]);
    }
});

it('menghapus satuan yang tidak dipakai', function () {
    $satuan = RkasRefSatuan::factory()->create();

    $this->delete(route('rkas.referensi.destroy', ['jenis' => 'satuan', 'uuid' => $satuan->uuid]))
        ->assertSessionHasNoErrors();

    expect(RkasRefSatuan::count())->toBe(0);
});

it('menolak jenis referensi yang tidak dikenal dan tamu', function () {
    $this->get(route('rkas.referensi.index', ['jenis' => 'ngawur']))->assertNotFound();

    auth()->logout();
    $this->get(route('rkas.referensi.index'))->assertRedirect(route('login'));
});

it('menolak rekening di luar kelompok 5.1 dan 5.2', function () {
    foreach (['4.1.02.01.01.0001', '5.3.02.01.01.0001', '6.1.02.01.01.0001'] as $kode) {
        $this->post(route('rkas.referensi.store', 'rekening'), ['tahun' => 2027, 'kode' => $kode, 'nama' => 'x'])
            ->assertSessionHasErrors('kode');
    }

    $this->post(route('rkas.referensi.store', 'rekening'), ['tahun' => 2027, 'kode' => '5.2.02.01.01.0001', 'nama' => 'Alat'])
        ->assertSessionHasNoErrors();

    expect(RkasRefRekening::count())->toBe(1);
});

it('memuat kode kegiatan resmi ARKAS lengkap dengan induknya dan aman diulang', function () {
    $this->post(route('rkas.referensi.muat-kegiatan'), ['tahun' => 2027])->assertSessionHasNoErrors();

    $level = fn (int $n) => RkasRefKegiatan::where('tahun', 2027)->where('level', $n)->count();
    expect($level(1))->toBe(7)
        ->and($level(2))->toBe(25)
        ->and($level(3))->toBe(151);

    $honor = RkasRefKegiatan::where('tahun', 2027)->where('kode', '07.12.01.')->firstOrFail();
    expect($honor->kode_induk)->toBe('07.12.')
        ->and($honor->uraian)->toBe('Pembayaran honor Guru/Pendidik')
        ->and(RkasRefKegiatan::where('tahun', 2027)->where('kode', '07.12.')->value('kode_induk'))->toBe('07.');

    // Hasil edit manual tidak tertimpa dan pemuatan kedua tidak menggandakan.
    $honor->update(['uraian' => 'Honor saya']);
    $this->post(route('rkas.referensi.muat-kegiatan'), ['tahun' => 2027]);

    expect(RkasRefKegiatan::where('tahun', 2027)->count())->toBe(183)
        ->and($honor->fresh()->uraian)->toBe('Honor saya')
        ->and(RkasRefKegiatan::where('tahun', 2026)->count())->toBe(0);
});

it('menolak memuat kode kegiatan tanpa tahun yang sah', function () {
    $this->post(route('rkas.referensi.muat-kegiatan'), ['tahun' => 1999])->assertSessionHasErrors('tahun');
});
