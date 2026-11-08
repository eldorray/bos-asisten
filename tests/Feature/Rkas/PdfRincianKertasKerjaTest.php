<?php

declare(strict_types=1);

use App\Actions\Rkas\PesanRkas;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Smalot\PdfParser\Parser;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->smp = Sekolah::where('nama', 'like', '%Garuda%')->firstOrFail();

    $this->buat = function (array $kk = []): RkasKertasKerja {
        $kertasKerja = RkasKertasKerja::factory()->create($kk + [
            'sekolah_id' => $this->smp->id, 'tahun' => 2027, 'pagu' => 100_000_000, 'sumber_dana' => SumberDanaRkas::BospReguler,
        ]);
        $program = RkasRefKegiatan::create(['tahun' => 2027, 'kode' => '07.', 'uraian' => 'Program Pendidikan', 'level' => 1]);
        $komponen = RkasRefKegiatan::create(['tahun' => 2027, 'kode' => '07.01.', 'uraian' => 'Komponen Sarana', 'level' => 2, 'kode_induk' => $program->kode]);
        $kegiatan = RkasRefKegiatan::create(['tahun' => 2027, 'kode' => '07.01.01.', 'uraian' => 'Kegiatan Pemeliharaan', 'level' => 3, 'kode_induk' => $komponen->kode]);
        $rekening = RkasRefRekening::create(['tahun' => 2027, 'kode' => '5.1.02.01.01.0001', 'nama' => 'Belanja Alat Tulis']);

        RkasBaris::factory()->create([
            'kertas_kerja_id' => $kertasKerja->id,
            'ref_kegiatan_id' => $kegiatan->id,
            'ref_rekening_id' => $rekening->id,
            'uraian' => 'Kertas HVS A4 untuk keperluan ujian semester seluruh kelas',
            'satuan' => 'Rim',
            'harga' => 55_000,
            'volume_bulan' => array_replace(array_fill_keys(range(1, 12), 0), [1 => 10, 7 => 5]),
        ]);

        return $kertasKerja;
    };

    $this->teks = fn ($respons): string => (new Parser)->parseContent($respons->getContent())->getText();
});

it('mengunduh PDF rincian berisi uraian, total, dan watermark draf', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->buat)();

    $respons = $this->get(route('rkas.pdf', $kk))->assertOk();

    expect($respons->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respons->headers->get('Content-Disposition'))->toContain('RKAS-2027-bosp_reguler-v0.0.pdf');

    $teks = ($this->teks)($respons);

    expect($teks)->toContain('Rincian Kertas Kerja')
        ->toContain('Kertas HVS A4')
        ->toContain('Komponen Sarana')
        ->toContain('Kegiatan Pemeliharaan')
        ->toContain(PesanRkas::rupiah(825_000))
        ->toContain('DRAF')
        ->toContain('Rp ');
});

it('memakai label RKAS setelah disahkan', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->buat)(['status' => StatusKertasKerja::Disahkan]);

    expect(($this->teks)($this->get(route('rkas.pdf', $kk))->assertOk()))->toContain('Rincian RKAS');
});

it('menolak tamu', function () {
    $kk = ($this->buat)();

    $this->get(route('rkas.pdf', $kk))->assertRedirect(route('login'));
});

it('menolak sekolah non-ARKAS', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->buat)();
    $this->smp->update(['sistem' => 'erkam']);

    $this->get(route('rkas.pdf', $kk))->assertNotFound();
});

it('membagi sheet panjang ke beberapa halaman dengan watermark, kepala tabel, dan judul kegiatan lanjutan', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->buat)();
    $kegiatan = RkasRefKegiatan::where('kode', '07.01.01.')->firstOrFail();
    $rekening = RkasRefRekening::where('kode', '5.1.02.01.01.0001')->firstOrFail();

    foreach (range(1, 60) as $n) {
        RkasBaris::factory()->create([
            'kertas_kerja_id' => $kk->id, 'ref_kegiatan_id' => $kegiatan->id, 'ref_rekening_id' => $rekening->id,
            'urutan' => $n, 'uraian' => "Perlengkapan nomor {$n} untuk seluruh kelas", 'satuan' => 'Paket sangat besar sekali', 'harga' => 10_000,
            'volume_bulan' => array_replace(array_fill_keys(range(1, 12), 0), [1 => 1]),
        ]);
    }

    $halaman = (new Parser)->parseContent(($this->get(route('rkas.pdf', $kk))->assertOk())->getContent())->getPages();

    expect(count($halaman))->toBeGreaterThanOrEqual(2);
    foreach ($halaman as $h) {
        expect($h->getText())->toContain('DRAF')->toContain('Rekening')->toContain('Jumlah');
    }
    expect($halaman[1]->getText())->toContain('(lanjutan)');
});

it('merender uraian berisi emoji dan tanda kutip lengkung', function () {
    $this->actingAs(User::factory()->create());
    $kk = ($this->buat)();
    RkasBaris::where('kertas_kerja_id', $kk->id)->update(['uraian' => 'Alat “tulis” 😀 kelas']);

    $teks = ($this->teks)($this->get(route('rkas.pdf', $kk))->assertOk());

    expect($teks)->toContain('Alat')->toContain('tulis')->toContain('kelas');
});

it('merender kertas kerja Lainnya dan kertas kerja kosong', function () {
    $this->actingAs(User::factory()->create());
    $lainnya = ($this->buat)(['sumber_dana' => SumberDanaRkas::Lainnya, 'pagu' => 0]);
    $kosong = RkasKertasKerja::factory()->create(['sekolah_id' => $this->smp->id, 'tahun' => 2028, 'pagu' => 5_000_000]);

    expect(($this->teks)($this->get(route('rkas.pdf', $lainnya))->assertOk()))->toContain('Lainnya')->toContain('DRAF')
        ->and(($this->teks)($this->get(route('rkas.pdf', $kosong))->assertOk()))->toContain('DRAF')->toContain('Sisa pagu');
});
