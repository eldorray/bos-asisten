<?php

declare(strict_types=1);

use App\Actions\Rkas\PesanRkas;
use App\Actions\Rkas\SimpanBarisRkas;
use App\Enums\JenisPtk;
use App\Enums\StatusKertasKerja;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPtk;
use App\Models\RkasRefBarang;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use App\Models\RkasRefSatuan;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();
    $this->actingAs(User::factory()->create());

    $this->kk = RkasKertasKerja::factory()->create(['sekolah_id' => $this->smp->id, 'tahun' => 2027]);
    $this->kegiatan = RkasRefKegiatan::factory()->create(['tahun' => 2027, 'kode' => '03.02.02.']);
    $this->rekening = RkasRefRekening::factory()->create(['tahun' => 2027, 'kode' => '5.1.02.01.01.0001']);
    RkasRefSatuan::factory()->create(['nama' => 'Buah']);

    $this->data = fn (array $ganti = []): array => array_replace([
        'ref_kegiatan_id' => $this->kegiatan->id,
        'ref_rekening_id' => $this->rekening->id,
        'ref_barang_id' => null,
        'ptk_id' => null,
        'uraian' => 'Spidol papan tulis',
        'satuan' => 'Buah',
        'harga' => 15_000,
        'volume_bulan' => [1 => 2, 7 => 3],
        'keterangan' => null,
    ], $ganti);

    $this->kirim = fn (array $ganti = []) => $this->post(route('rkas.baris.store', $this->kk), ($this->data)($ganti));
});

function volume(array $isi): array
{
    return array_replace(array_fill_keys(range(1, 12), 0), $isi);
}

it('menyimpan baris dan memberi urutan 001 lalu 002 per kegiatan dan rekening', function () {
    ($this->kirim)()->assertSessionHasNoErrors();
    ($this->kirim)(['uraian' => 'Spidol warna'])->assertSessionHasNoErrors();

    $lain = RkasRefRekening::factory()->create(['tahun' => 2027]);
    ($this->kirim)(['ref_rekening_id' => $lain->id, 'uraian' => 'Lain'])->assertSessionHasNoErrors();

    expect(RkasBaris::orderBy('id')->pluck('urutan')->all())->toBe([1, 2, 1]);

    $baris = RkasBaris::firstOrFail();
    expect($baris->volume())->toBe(5)
        ->and($baris->jumlah())->toBe(75_000)
        ->and($baris->volume_bulan)->toHaveCount(12);
});

it('menolak harga di bawah dan di atas batas SSH', function () {
    $barang = RkasRefBarang::factory()->create([
        'tahun' => 2027, 'kode_rekening' => $this->rekening->kode,
        'nama' => 'Kertas A4', 'satuan' => 'Buah', 'batas_bawah' => 10_000, 'batas_atas' => 20_000,
    ]);

    ($this->kirim)(['ref_barang_id' => $barang->id, 'harga' => 9_999])
        ->assertSessionHasErrors(['harga' => PesanRkas::SSH_BAWAH]);
    ($this->kirim)(['ref_barang_id' => $barang->id, 'harga' => 20_001])
        ->assertSessionHasErrors(['harga' => PesanRkas::SSH_ATAS]);
    ($this->kirim)(['ref_barang_id' => $barang->id, 'harga' => 20_000])->assertSessionHasNoErrors();

    $baris = RkasBaris::firstOrFail();
    expect($baris->uraian)->toBe('Kertas A4')->and($baris->satuan)->toBe('Buah');
});

it('tidak memeriksa batas atas bila batas atas nol', function () {
    $barang = RkasRefBarang::factory()->create([
        'tahun' => 2027, 'kode_rekening' => $this->rekening->kode,
        'nama' => 'Kertas A4', 'batas_bawah' => 10_000, 'batas_atas' => 0,
    ]);

    ($this->kirim)(['ref_barang_id' => $barang->id, 'harga' => 99_000_000])->assertSessionHasNoErrors();
    expect(RkasBaris::count())->toBe(1);
});

it('memaksa barang dari daftar bila rekening punya daftar barang', function () {
    RkasRefBarang::factory()->create(['tahun' => 2027, 'kode_rekening' => $this->rekening->kode]);

    ($this->kirim)()->assertSessionHasErrors(['ref_barang_id' => PesanRkas::WAJIB]);
    expect(RkasBaris::count())->toBe(0);
});

it('menerima uraian bebas bila rekening tidak punya daftar barang', function () {
    RkasRefBarang::factory()->create(['tahun' => 2027, 'kode_rekening' => '5.1.02.01.01.9999']);

    ($this->kirim)()->assertSessionHasNoErrors();
    expect(RkasBaris::count())->toBe(1);
});

it('memakai daftar barang tanpa rekening untuk rekening belanja modal 5.2', function () {
    $modal = RkasRefRekening::factory()->create(['tahun' => 2027, 'kode' => '5.2.02.05.01.0001']);
    $umum = RkasRefBarang::factory()->create(['tahun' => 2027, 'kode_rekening' => null, 'nama' => 'Meja']);

    ($this->kirim)(['ref_rekening_id' => $modal->id])
        ->assertSessionHasErrors(['ref_barang_id' => PesanRkas::WAJIB]);
    ($this->kirim)(['ref_rekening_id' => $modal->id, 'ref_barang_id' => $umum->id])
        ->assertSessionHasNoErrors();
    expect(RkasBaris::firstOrFail()->uraian)->toBe('Meja');
});

it('menolak barang milik rekening lain', function () {
    $barang = RkasRefBarang::factory()->create(['tahun' => 2027, 'kode_rekening' => '5.1.02.01.01.9999']);

    ($this->kirim)(['ref_barang_id' => $barang->id])->assertSessionHasErrors('ref_barang_id');
    expect(RkasBaris::count())->toBe(0);
});

it('menolak baris ganda walau beda huruf besar kecil', function () {
    ($this->kirim)()->assertSessionHasNoErrors();
    ($this->kirim)(['uraian' => '  SPIDOL PAPAN TULIS '])->assertSessionHasErrors(['uraian' => PesanRkas::GANDA]);
    ($this->kirim)(['harga' => 16_000])->assertSessionHasNoErrors();

    expect(RkasBaris::count())->toBe(2);
});

it('menolak baris tanpa volume di bulan mana pun', function () {
    ($this->kirim)(['volume_bulan' => volume([])])->assertSessionHasErrors(['volume_bulan' => PesanRkas::VOLUME_KOSONG]);
    expect(RkasBaris::count())->toBe(0);
});

it('menolak volume tujuh digit dan uraian 501 karakter', function () {
    ($this->kirim)(['volume_bulan' => [3 => 1_000_000]])
        ->assertSessionHasErrors(['volume_bulan.3' => PesanRkas::VOLUME_MAKS]);
    ($this->kirim)(['uraian' => str_repeat('a', 501)])
        ->assertSessionHasErrors(['uraian' => PesanRkas::URAIAN_MAKS]);
    ($this->kirim)(['uraian' => ''])->assertSessionHasErrors(['uraian' => PesanRkas::WAJIB]);
    ($this->kirim)(['harga' => 0])->assertSessionHasErrors(['harga' => PesanRkas::WAJIB]);
    expect(RkasBaris::count())->toBe(0);
});

it('menyusun uraian honor dari PTK dengan NUPTK', function () {
    $honor = RkasRefKegiatan::factory()->create(['tahun' => 2027, 'kode' => '07.12.01.']);
    $ptk = RkasPtk::factory()->create(['sekolah_id' => $this->smp->id, 'nama' => 'Ani', 'nuptk' => '1234567890123456']);
    $tanpa = RkasPtk::factory()->create(['sekolah_id' => $this->smp->id, 'nama' => 'Budi', 'nuptk' => null]);

    ($this->kirim)(['ref_kegiatan_id' => $honor->id, 'uraian' => 'diabaikan'])
        ->assertSessionHasErrors(['ptk_id' => PesanRkas::WAJIB]);
    ($this->kirim)(['ref_kegiatan_id' => $honor->id, 'ptk_id' => $ptk->id, 'uraian' => 'diabaikan'])
        ->assertSessionHasNoErrors();
    ($this->kirim)(['ref_kegiatan_id' => $honor->id, 'ptk_id' => $tanpa->id, 'uraian' => ''])
        ->assertSessionHasNoErrors();

    expect(RkasBaris::orderBy('id')->pluck('uraian')->all())->toBe(['Ani (1234567890123456)', 'Budi']);
});

it('menolak PTK yang jenisnya tidak cocok dengan kegiatan honor', function () {
    $honor = RkasRefKegiatan::factory()->create(['tahun' => 2027, 'kode' => '07.12.02.']);
    $guru = RkasPtk::factory()->create(['sekolah_id' => $this->smp->id, 'jenis' => JenisPtk::Guru]);

    ($this->kirim)(['ref_kegiatan_id' => $honor->id, 'ptk_id' => $guru->id])
        ->assertSessionHasErrors(['ptk_id' => sprintf(PesanRkas::PTK_JENIS, 'Administrasi', 'Guru')]);
    expect(RkasBaris::count())->toBe(0);
});

it('mengubah baris tanpa mengubah urutannya', function () {
    ($this->kirim)()->assertSessionHasNoErrors();
    ($this->kirim)(['uraian' => 'Spidol warna'])->assertSessionHasNoErrors();
    $kedua = RkasBaris::orderBy('id')->get()->last();

    $this->patch(route('rkas.baris.update', $kedua), ($this->data)(['uraian' => 'Spidol merah']))
        ->assertSessionHasNoErrors();

    expect($kedua->fresh()->uraian)->toBe('Spidol merah')->and($kedua->fresh()->urutan)->toBe(2);
});

it('tidak menganggap baris yang sedang diubah sebagai ganda', function () {
    ($this->kirim)()->assertSessionHasNoErrors();
    $baris = RkasBaris::firstOrFail();

    $this->patch(route('rkas.baris.update', $baris), ($this->data)(['volume_bulan' => [2 => 9]]))
        ->assertSessionHasNoErrors();

    expect($baris->fresh()->volume())->toBe(9);
});

it('menghapus baris', function () {
    ($this->kirim)()->assertSessionHasNoErrors();

    $this->delete(route('rkas.baris.destroy', RkasBaris::firstOrFail()))->assertRedirect();

    expect(RkasBaris::count())->toBe(0);
});

it('menolak mengubah baris saat kertas kerja Siap', function () {
    ($this->kirim)()->assertSessionHasNoErrors();
    $baris = RkasBaris::firstOrFail();
    $this->kk->update(['status' => StatusKertasKerja::Siap]);
    $pesan = sprintf(PesanRkas::TERKUNCI, 'Siap');

    ($this->kirim)(['uraian' => 'Baru'])->assertSessionHasErrors(['kertas_kerja' => $pesan]);
    $this->patch(route('rkas.baris.update', $baris), ($this->data)(['uraian' => 'Baru']))
        ->assertSessionHasErrors(['kertas_kerja' => $pesan]);
    $this->delete(route('rkas.baris.destroy', $baris))->assertSessionHasErrors(['kertas_kerja' => $pesan]);

    expect(RkasBaris::count())->toBe(1)->and($baris->fresh()->uraian)->toBe('Spidol papan tulis');
});

it('menolak action langsung saat kertas kerja terkunci', function () {
    $this->kk->update(['status' => StatusKertasKerja::Disahkan]);

    expect(fn () => app(SimpanBarisRkas::class)->simpan($this->kk, null, [
        ...($this->data)(), 'volume_bulan' => volume([1 => 1]),
    ]))->toThrow(ValidationException::class);

    $baris = RkasBaris::factory()->create(['kertas_kerja_id' => $this->kk->id]);
    expect(fn () => app(SimpanBarisRkas::class)->hapus($baris))->toThrow(ValidationException::class);
});

it('menolak kegiatan yang kedaluwarsa untuk baris baru', function () {
    $this->kegiatan->update(['kedaluwarsa' => now()->toDateString()]);

    ($this->kirim)()->assertSessionHasErrors('ref_kegiatan_id');
    expect(RkasBaris::count())->toBe(0);
});

it('menolak satuan yang tidak ada di referensi', function () {
    ($this->kirim)(['satuan' => 'Gelombang'])->assertSessionHasErrors('satuan');
});

it('tetap bisa mengubah baris yang kegiatannya kedaluwarsa sesudah dibuat', function () {
    ($this->kirim)()->assertSessionHasNoErrors();
    $baris = RkasBaris::firstOrFail();
    $this->kegiatan->update(['kedaluwarsa' => now()->toDateString()]);

    $this->patch(route('rkas.baris.update', $baris), ($this->data)(['volume_bulan' => [1 => 4]]))
        ->assertSessionHasNoErrors();

    expect($baris->fresh()->volume())->toBe(4);
});

it('mengirim baris dan pilihan ke halaman kertas kerja', function () {
    ($this->kirim)()->assertSessionHasNoErrors();
    RkasRefKegiatan::factory()->create(['tahun' => 2027, 'kedaluwarsa' => '2020-01-01']);

    $this->get(route('rkas.show', $this->kk))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rkas/Show')
            ->has('baris', 1)
            ->where('baris.0.uraian', 'Spidol papan tulis')
            ->where('baris.0.volume', 5)
            ->where('baris.0.jumlah', 75_000)
            ->where('baris.0.masalah', [])
            ->where('baris.0.kegiatan.kode', '03.02.02.')
            ->has('pilihan.kegiatan', 1)
            ->has('pilihan.rekening', 1)
            ->has('pilihan.satuan', 1)
            ->has('pilihan.ptk', 0)
            ->missing('baris.0.created_at'));
});

it('menolak baris untuk sekolah yang bukan ARKAS', function () {
    $erkam = Sekolah::where('id', '!=', $this->smp->id)->firstOrFail();
    $kk = RkasKertasKerja::factory()->create(['sekolah_id' => $erkam->id]);

    $this->post(route('rkas.baris.store', $kk), ($this->data)())->assertNotFound();
});

it('meminta barang, bukan uraian, bila rekening punya daftar barang dan uraian tidak dikirim', function () {
    RkasRefBarang::factory()->create(['tahun' => 2027, 'kode_rekening' => $this->rekening->kode]);
    $isi = Arr::except(($this->data)(), ['uraian', 'satuan']);

    $this->post(route('rkas.baris.store', $this->kk), $isi)
        ->assertSessionHasErrors(['ref_barang_id' => PesanRkas::WAJIB])
        ->assertSessionDoesntHaveErrors(['uraian', 'satuan']);
});

it('meminta PTK, bukan uraian, bila kegiatan honor dan uraian tidak dikirim', function () {
    $honor = RkasRefKegiatan::factory()->create(['tahun' => 2027, 'kode' => '07.12.01.']);
    $isi = Arr::except(($this->data)(['ref_kegiatan_id' => $honor->id]), ['uraian']);

    $this->post(route('rkas.baris.store', $this->kk), $isi)
        ->assertSessionHasErrors(['ptk_id' => PesanRkas::WAJIB])
        ->assertSessionDoesntHaveErrors(['uraian']);
});

it('memindahkan urutan ke kelompok tujuan saat kegiatan atau rekening berubah, selain itu tetap', function () {
    ($this->kirim)()->assertSessionHasNoErrors();
    ($this->kirim)(['uraian' => 'Spidol warna'])->assertSessionHasNoErrors();
    $lain = RkasRefRekening::factory()->create(['tahun' => 2027]);
    ($this->kirim)(['ref_rekening_id' => $lain->id, 'uraian' => 'Lain'])->assertSessionHasNoErrors();
    [$pertama, $kedua] = RkasBaris::orderBy('id')->get();

    $this->patch(route('rkas.baris.update', $pertama), ($this->data)(['ref_rekening_id' => $lain->id]))
        ->assertSessionHasNoErrors();
    expect($pertama->fresh()->urutan)->toBe(2);

    $this->patch(route('rkas.baris.update', $kedua), ($this->data)(['uraian' => 'Spidol warna', 'harga' => 17_000]))
        ->assertSessionHasNoErrors();
    expect($kedua->fresh()->urutan)->toBe(2);
});

it('membatasi harga sampai 700 miliar', function () {
    ($this->kirim)(['harga' => 700_000_000_001])->assertSessionHasErrors(['harga' => PesanRkas::HARGA_MAKS]);
    expect(RkasBaris::count())->toBe(0);

    ($this->kirim)(['harga' => 700_000_000_000, 'volume_bulan' => array_fill(1, 12, 999_999)])
        ->assertSessionHasNoErrors();
    expect(RkasBaris::firstOrFail()->jumlah())->toBe(700_000_000_000 * 12 * 999_999);
});

it('mengurutkan baris menurut kegiatan, rekening, lalu urutan', function () {
    $rekB = RkasRefRekening::factory()->create(['tahun' => 2027, 'kode' => '5.1.02.01.01.0009']);
    $rekA = RkasRefRekening::factory()->create(['tahun' => 2027, 'kode' => '5.1.02.01.01.0002']);
    RkasBaris::factory()->create(['kertas_kerja_id' => $this->kk->id, 'ref_kegiatan_id' => $this->kegiatan->id, 'ref_rekening_id' => $rekB->id, 'uraian' => 'b']);
    RkasBaris::factory()->create(['kertas_kerja_id' => $this->kk->id, 'ref_kegiatan_id' => $this->kegiatan->id, 'ref_rekening_id' => $rekA->id, 'uraian' => 'a']);

    $this->get(route('rkas.show', $this->kk))->assertInertia(fn (Assert $page) => $page
        ->where('baris.0.uraian', 'a')
        ->where('baris.1.uraian', 'b'));
});

it('menyertakan referensi milik baris yang sudah kedaluwarsa agar tetap bisa diubah', function () {
    $barang = RkasRefBarang::factory()->create(['tahun' => 2027, 'kode_rekening' => $this->rekening->kode, 'nama' => 'Kertas']);
    ($this->kirim)(['ref_barang_id' => $barang->id])->assertSessionHasNoErrors();
    $barang->update(['kedaluwarsa' => now()->toDateString()]);
    $this->rekening->update(['kedaluwarsa' => now()->toDateString()]);

    $this->get(route('rkas.show', $this->kk))->assertInertia(fn (Assert $page) => $page
        ->where('baris.0.barang.id', $barang->id)
        ->where('baris.0.kegiatan_honor', false)
        ->has('pilihan.barang', 0)
        ->has('pilihan.rekening', 0));

    $this->patch(route('rkas.baris.update', RkasBaris::firstOrFail()), ($this->data)(['ref_barang_id' => $barang->id, 'volume_bulan' => [1 => 7]]))
        ->assertSessionHasNoErrors();
});
