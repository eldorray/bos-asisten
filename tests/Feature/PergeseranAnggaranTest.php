<?php

use App\Actions\SusunBlokBulan;
use App\Actions\SusunLayarBulan;
use App\Enums\SistemAnggaran;
use App\Enums\StatusManual;
use App\Impor\AnggaranTerbaca;
use App\Impor\BarisAnggaran;
use App\Impor\SimpanAnggaran;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    $this->actingAs(User::factory()->create());
});

function barisUji(string $uraian, int $jumlah, string $koefisien = '1 Paket'): BarisAnggaran
{
    return new BarisAnggaran('5.1.02.01.01.0024', '05.08.02.', $uraian, $koefisien, $jumlah, 0, $jumlah, null);
}

/**
 * @param  list<BarisAnggaran>  $baris
 */
function dokumenUji(Sekolah $sekolah, int $bulan, array $baris): AnggaranTerbaca
{
    return new AnggaranTerbaca(
        SistemAnggaran::Arkas,
        $sekolah->identitas,
        2026,
        $bulan,
        'BOSP Reguler',
        $baris,
        (int) array_sum(array_map(fn (BarisAnggaran $b): int => $b->jumlah, $baris)),
    );
}

function barisKegiatan(Sekolah $sekolah, int $bulan = 3, array $atur = []): Kegiatan
{
    return Kegiatan::factory()->create($atur + ['sekolah_id' => $sekolah->id, 'tahun' => 2026, 'bulan' => $bulan, 'jumlah' => 100000]);
}

function kuitansiUntuk(Kegiatan $kegiatan, int $jumlah): Kuitansi
{
    $kuitansi = Kuitansi::factory()->create([
        'sekolah_id' => $kegiatan->sekolah_id,
        'tahun' => $kegiatan->tahun,
        'bulan' => $kegiatan->bulan,
        'jumlah' => $jumlah,
    ]);
    $kegiatan->kuitansi()->attach($kuitansi);

    return $kuitansi;
}

function layarUji(int $bulan): array
{
    $hasil = app(SusunLayarBulan::class)->susun(2026, $bulan);

    return $hasil['sekolah'][array_search('Uji', array_column($hasil['sekolah'], 'nama'), true)];
}

function sekolahUji(): Sekolah
{
    return Sekolah::factory()->create(['nama' => 'Uji']);
}

describe('pratinjau impor rinci', function () {
    it('merinci baris baru, berubah, dan hilang beserta nasib berkasnya', function () {
        $sekolah = sekolahUji();
        $simpan = app(SimpanAnggaran::class);
        $simpan->simpan(dokumenUji($sekolah, 3, [
            barisUji('Tinta Printer', 300000, '3 Botol'),
            barisUji('Proyektor', 5000000),
            barisUji('Map Plastik', 50000),
        ]), 'lama.pdf');

        $proyektor = Kegiatan::where('uraian', 'Proyektor')->firstOrFail();
        Foto::factory()->create(['pemilik_type' => $proyektor->getMorphClass(), 'pemilik_id' => $proyektor->id]);
        kuitansiUntuk(Kegiatan::where('uraian', 'Tinta Printer')->firstOrFail(), 300000);

        $rincian = $simpan->bandingkan(dokumenUji($sekolah, 3, [
            barisUji('Tinta Printer', 200000, '2 Botol'),
            barisUji('Buku Paket', 4000000),
        ]))->rincian;

        expect($rincian['baru'])->toBe([['nama' => 'Buku Paket', 'jumlah' => 4000000]])
            ->and($rincian['berubah'])->toBe([[
                'nama' => 'Tinta Printer',
                'koefisienLama' => '3 Botol',
                'koefisienBaru' => '2 Botol',
                'jumlahLama' => 300000,
                'jumlahBaru' => 200000,
                'adaKuitansi' => true,
            ]])
            ->and($rincian['hilang'])->toEqualCanonicalizing([
                ['nama' => 'Proyektor', 'jumlah' => 5000000, 'disimpan' => true],
                ['nama' => 'Map Plastik', 'jumlah' => 50000, 'disimpan' => false],
            ]);
    });

    it('bandingkan tidak mengubah data', function () {
        $sekolah = sekolahUji();

        app(SimpanAnggaran::class)->bandingkan(dokumenUji($sekolah, 3, [barisUji('Tinta', 1000)]));

        expect(Kegiatan::count())->toBe(0);
    });
});

describe('status perlu diperiksa', function () {
    it('menandai kuitansi yang nominalnya beda dari anggaran', function () {
        $sekolah = sekolahUji();
        kuitansiUntuk(barisKegiatan($sekolah), 90000);

        expect(layarUji(3)['kegiatan'][0]['perluDiperiksa'])->toContain('Rp 90.000')->toContain('Rp 100.000');
    });

    it('meminta cek BKU untuk baris berkuitansi yang hilang dari anggaran', function () {
        $sekolah = sekolahUji();
        kuitansiUntuk(barisKegiatan($sekolah, 3, ['hilang_dari_anggaran' => true]), 100000);

        expect(layarUji(3)['kegiatan'][0]['perluDiperiksa'])->toContain('BKU');
    });

    it('menandai baris hilang yang punya foto tanpa kuitansi', function () {
        $sekolah = sekolahUji();
        $kegiatan = barisKegiatan($sekolah, 3, ['hilang_dari_anggaran' => true]);
        Foto::factory()->create(['pemilik_type' => $kegiatan->getMorphClass(), 'pemilik_id' => $kegiatan->id]);

        expect(layarUji(3)['kegiatan'][0]['perluDiperiksa'])->toContain('Tidak ada lagi di anggaran');
    });

    it('tidak menandai kegiatan yang cocok atau sudah ditandai manual', function () {
        $sekolah = sekolahUji();
        kuitansiUntuk(barisKegiatan($sekolah), 100000);
        barisKegiatan($sekolah, 3, ['hilang_dari_anggaran' => true, 'status_manual' => StatusManual::TidakDilaksanakan]);

        expect(array_column(layarUji(3)['kegiatan'], 'perluDiperiksa'))->toBe([null, null]);
    });

    it('menjumlah semua baris saat kuitansi rapel', function () {
        $sekolah = sekolahUji();
        $februari = barisKegiatan($sekolah, 2);
        $maret = barisKegiatan($sekolah, 3);
        $kuitansi = kuitansiUntuk($maret, 200000);
        $februari->kuitansi()->attach($kuitansi);

        expect(layarUji(3)['kegiatan'][0]['perluDiperiksa'])->toBeNull();
    });

    it('terlihat di halaman rincian', function () {
        $kegiatan = barisKegiatan(sekolahUji());
        kuitansiUntuk($kegiatan, 90000);

        $this->get(route('kegiatan.show', $kegiatan))
            ->assertInertia(fn (Assert $page) => $page->where('kegiatan.perluDiperiksa', fn (?string $v) => str_contains((string) $v, 'Rp 90.000')));
    });
});

describe('ditunda ke bulan berikutnya', function () {
    it('memindahkan baris ARKAS ke bulan pelaksanaan tanpa mengubah bulan anggaran', function () {
        $sekolah = sekolahUji();
        $kegiatan = barisKegiatan($sekolah);

        $this->patch(route('kegiatan.update', $kegiatan), ['bulan_pelaksanaan' => 4])->assertSessionHasNoErrors();

        expect($kegiatan->fresh()->bulan)->toBe(3)->and($kegiatan->fresh()->bulan_pelaksanaan)->toBe(4);

        $maret = layarUji(3);
        $april = layarUji(4);

        expect($maret['kegiatan'])->toBe([])
            ->and($maret['total'])->toBe(0)
            ->and(array_column($maret['ditunda'], 'id'))->toBe([$kegiatan->id])
            ->and($maret['ditunda'][0]['bulanPelaksanaan'])->toBe(4)
            ->and(array_column($april['kegiatan'], 'id'))->toBe([$kegiatan->id])
            ->and($april['kegiatan'][0]['ditundaDari'])->toBe(3)
            ->and($april['total'])->toBe(100000)
            ->and($april['ditunda'])->toBe([]);
    });

    it('memindahkan hitungan tunggakan', function () {
        barisKegiatan(sekolahUji());
        $this->travelTo('2026-03-10');

        $kegiatan = Kegiatan::firstOrFail();
        expect(app(SusunLayarBulan::class)->jumlahMenunggak(2026, 3))->toBe(1);

        $kegiatan->update(['bulan_pelaksanaan' => 4]);

        $hasil = app(SusunLayarBulan::class)->susun(2026, 3);

        expect(app(SusunLayarBulan::class)->jumlahMenunggak(2026, 3))->toBe(0)
            ->and(app(SusunLayarBulan::class)->jumlahMenunggak(2026, 4))->toBe(1)
            ->and($hasil['menunggakPerBulan'][3])->toBe(0)
            ->and($hasil['menunggakPerBulan'][4])->toBe(1);
    });

    it('boleh lintas tahap sampai Desember, tetapi tidak mundur dan tidak lewat tahun', function () {
        $kegiatan = barisKegiatan(sekolahUji(), 6);

        $this->patch(route('kegiatan.update', $kegiatan), ['bulan_pelaksanaan' => 7])->assertSessionHasNoErrors();
        $this->patch(route('kegiatan.update', $kegiatan), ['bulan_pelaksanaan' => 12])->assertSessionHasNoErrors();
        $this->patch(route('kegiatan.update', $kegiatan), ['bulan_pelaksanaan' => 13])->assertSessionHasErrors('bulan_pelaksanaan');
        $this->patch(route('kegiatan.update', $kegiatan), ['bulan_pelaksanaan' => 6])->assertSessionHasErrors('bulan_pelaksanaan');
        $this->patch(route('kegiatan.update', $kegiatan), ['bulan_pelaksanaan' => 5])->assertSessionHasErrors('bulan_pelaksanaan');

        expect($kegiatan->fresh()->bulan_pelaksanaan)->toBe(12);
    });

    it('bisa dibatalkan selama belum ada kuitansi', function () {
        $kegiatan = barisKegiatan(sekolahUji(), 3, ['bulan_pelaksanaan' => 4]);

        $this->patch(route('kegiatan.update', $kegiatan), ['bulan_pelaksanaan' => null])->assertSessionHasNoErrors();

        expect($kegiatan->fresh()->bulan_pelaksanaan)->toBeNull();
    });

    it('ditolak kalau kuitansi sudah ada, termasuk saat membatalkan', function () {
        $sekolah = sekolahUji();
        $biasa = barisKegiatan($sekolah);
        kuitansiUntuk($biasa, 100000);
        $ditunda = barisKegiatan($sekolah, 3, ['bulan_pelaksanaan' => 4]);
        kuitansiUntuk($ditunda, 100000);

        $this->patch(route('kegiatan.update', $biasa), ['bulan_pelaksanaan' => 4])->assertSessionHasErrors('bulan_pelaksanaan');
        $this->patch(route('kegiatan.update', $ditunda), ['bulan_pelaksanaan' => null])->assertSessionHasErrors('bulan_pelaksanaan');
    });

    it('ditolak untuk ERKAM, anggota acara, status manual, dan baris hilang', function (string $kasus) {
        $sekolah = $kasus === 'erkam' ? Sekolah::factory()->erkam()->create(['nama' => 'Uji']) : sekolahUji();
        $atur = match ($kasus) {
            'acara' => ['acara_id' => Acara::factory()->create(['sekolah_id' => $sekolah->id])->id],
            'manual' => ['status_manual' => StatusManual::TidakDilaksanakan],
            'hilang' => ['hilang_dari_anggaran' => true],
            default => [],
        };

        $this->patch(route('kegiatan.update', barisKegiatan($sekolah, 3, $atur)), ['bulan_pelaksanaan' => 4])
            ->assertSessionHasErrors('bulan_pelaksanaan');
    })->with(['erkam', 'acara', 'manual', 'hilang']);

    it('kuitansinya masuk bulan pelaksanaan dan tidak dianggap rapel', function () {
        $kegiatan = barisKegiatan(sekolahUji(), 3, ['bulan_pelaksanaan' => 4, 'nama' => 'Tinta']);

        $this->get(route('kegiatan.show', $kegiatan))
            ->assertInertia(fn (Assert $page) => $page
                ->where('nilaiAwal.nomor', fn (string $n) => str_contains($n, '/04/'))
                ->where('nilaiAwal.untuk_pembayaran', fn (string $u) => str_contains($u, 'April')));

        $this->post(route('kuitansi.store', $kegiatan), [
            'nomor' => '001/04/UJI/2026',
            'untuk_pembayaran' => 'Tinta April 2026',
            'penerima' => 'Toko',
            'tanggal_lunas' => '2026-04-10',
            'jumlah' => 100000,
        ])->assertSessionHasNoErrors();

        expect($kegiatan->kuitansi()->firstOrFail()->bulan)->toBe(4)
            ->and(layarUji(4)['kegiatan'][0]['status'])->not->toBe('dirapel')
            ->and(layarUji(4)['kegiatan'][0]['rapel'])->toBeNull();
    });

    it('PDF mengikuti bulan pelaksanaan', function () {
        $sekolah = sekolahUji();
        $kegiatan = barisKegiatan($sekolah, 3, ['bulan_pelaksanaan' => 4]);
        Foto::factory()->create(['pemilik_type' => $kegiatan->getMorphClass(), 'pemilik_id' => $kegiatan->id]);

        expect(SusunBlokBulan::untuk($sekolah, 2026, 3))->toBe([])
            ->and(SusunBlokBulan::untuk($sekolah, 2026, 4))->toHaveCount(1);
    });

    it('bertahan saat PDF bulan anggaran diunggah ulang', function () {
        $sekolah = sekolahUji();
        $simpan = app(SimpanAnggaran::class);
        $simpan->simpan(dokumenUji($sekolah, 3, [barisUji('Tinta', 100000)]), 'a.pdf');
        $kegiatan = Kegiatan::firstOrFail();
        $kegiatan->update(['bulan_pelaksanaan' => 5]);

        $simpan->simpan(dokumenUji($sekolah, 3, [barisUji('Tinta', 90000)]), 'b.pdf');
        expect($kegiatan->fresh()->bulan_pelaksanaan)->toBe(5)->and($kegiatan->fresh()->jumlah)->toBe(90000);

        // Pilihan pemilik tidak boleh hilang diam-diam walau belum ada berkas.
        $simpan->simpan(dokumenUji($sekolah, 3, [barisUji('Lain', 90000)]), 'c.pdf');
        expect($kegiatan->fresh()?->hilang_dari_anggaran)->toBeTrue();
    });

    it('tidak ikut rapel dan tidak ikut saran acara', function () {
        $sekolah = sekolahUji();
        $sama = ['uraian' => 'Honor', 'kode' => '5.1', 'kode_program' => '01.'];
        $ditunda = barisKegiatan($sekolah, 3, $sama + ['bulan_pelaksanaan' => 4]);
        $april = barisKegiatan($sekolah, 4, $sama);

        $this->get(route('kegiatan.show', $april))
            ->assertInertia(fn (Assert $page) => $page->where('kandidatRapel', []));

        $this->get(route('kegiatan.show', $ditunda))
            ->assertInertia(fn (Assert $page) => $page->where('kandidatRapel', [])->where('kandidatAcara', []));
    });

    it('menyediakan pilihan bulan di rincian ARKAS saja', function () {
        $arkas = barisKegiatan(sekolahUji());
        $erkam = barisKegiatan(Sekolah::factory()->erkam()->create());

        $this->get(route('kegiatan.show', $arkas))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tunda.pilihan', [4, 5, 6, 7, 8, 9, 10, 11, 12])
                ->where('tunda.alasan', null)
                ->where('kegiatan.bulanPelaksanaan', null));

        $this->get(route('kegiatan.show', $erkam))
            ->assertInertia(fn (Assert $page) => $page->where('tunda', null));

        kuitansiUntuk($arkas, 100000);

        $this->get(route('kegiatan.show', $arkas))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tunda.pilihan', [])
                ->where('tunda.alasan', fn (string $a) => str_contains($a, 'kuitansi')));
    });
});
