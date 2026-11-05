<?php

use App\Actions\NilaiAwalKuitansi;
use App\Actions\SimpanKuitansi;
use App\Impor\PdfAnggaranParser;
use App\Impor\SimpanAnggaran;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function sekolahSmp(array $ubah = []): Sekolah
{
    return Sekolah::factory()->create($ubah + ['kode_bukti' => 'SMPG']);
}

function sekolahMi(array $ubah = []): Sekolah
{
    return Sekolah::factory()->erkam()->create($ubah + ['kode_bukti' => 'MIDH']);
}

/**
 * @param  array<string, mixed>  $ubah
 */
function kegiatanGaji(Sekolah $sekolah, int $bulan, array $ubah = []): Kegiatan
{
    return Kegiatan::factory()->create($ubah + [
        'sekolah_id' => $sekolah->id,
        'tahun' => 2026,
        'bulan' => $bulan,
        'kode' => '5.1.02.01.01.0012',
        'kode_program' => '02.01',
        'uraian' => 'Gaji/Honor Rutin PTK/Operator',
        'nama' => 'Gaji/Honor Rutin PTK/Operator',
        'harga' => 15_960_000,
        'pajak' => 0,
        'jumlah' => 15_960_000,
    ]);
}

/**
 * @param  array<string, mixed>  $ubah
 * @return array<string, mixed>
 */
function isianKuitansi(array $ubah = []): array
{
    return $ubah + [
        'nomor' => '001/01/SMPG/2026',
        'untuk_pembayaran' => 'Honor GBPNS Januari 2026 a/n Abdul Hamid dkk',
        'penerima' => 'Abdul Hamid',
        'tanggal_lunas' => '2026-01-20',
        'jumlah' => 15_960_000,
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('nomor pertama mengikuti sistem sekolah', function () {
    $smp = kegiatanGaji(sekolahSmp(), 1);
    $mi = Kegiatan::factory()->create(['sekolah_id' => sekolahMi()->id, 'tahun' => 2026, 'bulan' => 7, 'tahap' => 1]);

    $awalSmp = NilaiAwalKuitansi::untuk($smp);
    $awalMi = NilaiAwalKuitansi::untuk($mi);

    expect($awalSmp['nomor'])->toBe('001/01/SMPG/2026')
        ->and($awalSmp['sumber_dana'])->toBe('BOSP Reguler 2026')
        ->and($awalSmp['jumlah'])->toBe(15_960_000)
        ->and($awalSmp['untuk_pembayaran'])->toBe('Gaji/Honor Rutin PTK/Operator Januari 2026')
        ->and($awalSmp['penerima'])->toBeNull()
        ->and($awalMi['nomor'])->toBe('001/T1/MIDH/2026')
        ->and($awalMi['sumber_dana'])->toBe('Dana BOS Tahap 1 2026');
});

test('nomor awal sekolah dihormati dan nomor urut naik terus', function () {
    $sekolah = sekolahSmp(['nomor_awal' => [2026 => 40]]);
    $januari = kegiatanGaji($sekolah, 1);

    expect(NilaiAwalKuitansi::untuk($januari)['nomor'])->toBe('040/01/SMPG/2026');

    Kuitansi::factory()->create(['sekolah_id' => $sekolah->id, 'nomor_urut' => 45, 'nomor' => '045/01/SMPG/2026']);

    expect(NilaiAwalKuitansi::untuk($januari)['nomor'])->toBe('046/01/SMPG/2026');
});

test('nomor diubah manual tidak membuat nomor berikutnya bentrok', function () {
    $sekolah = sekolahSmp();
    $januari = kegiatanGaji($sekolah, 1);
    $februari = kegiatanGaji($sekolah, 2, ['uraian' => 'Listrik', 'nama' => 'Listrik', 'kode' => '5.1.02.01.01.0099']);

    $this->post(route('kuitansi.store', $januari), isianKuitansi())->assertSessionHasNoErrors();
    $kuitansi = Kuitansi::firstOrFail();
    $this->patch(route('kuitansi.update', $kuitansi), isianKuitansi(['nomor' => '002/01/SMPG/2026']))->assertSessionHasNoErrors();

    expect($kuitansi->fresh()->nomor_urut)->toBe(2)
        ->and(NilaiAwalKuitansi::untuk($februari)['nomor'])->toBe('003/02/SMPG/2026');
});

test('hapus kuitansi terakhir lalu buat lagi berhasil', function () {
    $sekolah = sekolahSmp();
    $januari = kegiatanGaji($sekolah, 1);
    $februari = kegiatanGaji($sekolah, 2, ['uraian' => 'Listrik', 'nama' => 'Listrik', 'kode' => '5.1.02.01.01.0099']);

    $this->post(route('kuitansi.store', $januari), isianKuitansi())->assertSessionHasNoErrors();
    $this->post(route('kuitansi.store', $februari), isianKuitansi([
        'nomor' => NilaiAwalKuitansi::untuk($februari)['nomor'],
        'tanggal_lunas' => '2026-02-10',
    ]))->assertSessionHasNoErrors();

    $terakhir = Kuitansi::where('bulan', 2)->firstOrFail();
    expect($terakhir->nomor)->toBe('002/02/SMPG/2026');

    $this->delete(route('kuitansi.destroy', $terakhir))->assertRedirect();

    $baru = NilaiAwalKuitansi::untuk($februari)['nomor'];
    expect($baru)->toBe('002/02/SMPG/2026');

    $this->post(route('kuitansi.store', $februari), isianKuitansi(['nomor' => $baru, 'tanggal_lunas' => '2026-02-10']))
        ->assertSessionHasNoErrors();
    expect(Kuitansi::count())->toBe(2);
});

test('nomor ganda secara manual menjadi galat validasi', function () {
    $sekolah = sekolahSmp();
    $januari = kegiatanGaji($sekolah, 1);
    $februari = kegiatanGaji($sekolah, 2, ['uraian' => 'Listrik', 'nama' => 'Listrik', 'kode' => '5.1.02.01.01.0099']);

    $this->post(route('kuitansi.store', $januari), isianKuitansi())->assertSessionHasNoErrors();

    $this->post(route('kuitansi.store', $februari), isianKuitansi(['tanggal_lunas' => '2026-02-10']))
        ->assertSessionHasErrors('nomor');

    expect(Kuitansi::count())->toBe(1);

    $lain = kegiatanGaji($sekolah, 3, ['uraian' => 'Air', 'nama' => 'Air', 'kode' => '5.1.02.01.01.0098']);
    $this->post(route('kuitansi.store', $lain), isianKuitansi(['nomor' => '002/03/SMPG/2026', 'tanggal_lunas' => '2026-03-10']))
        ->assertSessionHasNoErrors();
    $this->patch(route('kuitansi.update', Kuitansi::where('bulan', 3)->firstOrFail()), isianKuitansi())
        ->assertSessionHasErrors('nomor');
});

test('nomor yang sama di sekolah atau tahun lain tidak bentrok', function () {
    $lain = sekolahSmp(['kode_bukti' => 'SMPX']);
    Kuitansi::factory()->create(['sekolah_id' => $lain->id, 'nomor_urut' => 1, 'nomor' => '001/01/SMPG/2026']);

    $this->post(route('kuitansi.store', kegiatanGaji(sekolahSmp(), 1)), isianKuitansi())->assertSessionHasNoErrors();
});

test('benturan indeks unik saat simpan diubah menjadi galat validasi', function () {
    $sekolah = sekolahSmp();
    $januari = kegiatanGaji($sekolah, 1);
    $februari = kegiatanGaji($sekolah, 2, ['uraian' => 'Listrik', 'nama' => 'Listrik', 'kode' => '5.1.02.01.01.0099']);
    $data = isianKuitansi();

    app(SimpanKuitansi::class)->buat([$januari->id], $data);

    // Melewati validasi seperti balapan dua permintaan: indeks basis data yang menahan.
    expect(fn () => app(SimpanKuitansi::class)->buat([$februari->id], $data))->toThrow(ValidationException::class);
    expect(Kuitansi::count())->toBe(1)->and($februari->kuitansi()->count())->toBe(0);
});

test('teks dan penerima kuitansi sebelumnya diingat dengan bulan diganti', function () {
    $sekolah = sekolahSmp();
    $april = kegiatanGaji($sekolah, 4);
    $mei = kegiatanGaji($sekolah, 5);
    $kuitansi = Kuitansi::factory()->create([
        'sekolah_id' => $sekolah->id, 'bulan' => 4, 'nomor_urut' => 1, 'nomor' => '001/04/SMPG/2026',
        'untuk_pembayaran' => 'Honor GBPNS April 2026 a/n Abdul Hamid dkk', 'penerima' => 'Abdul Hamid',
    ]);
    $kuitansi->kegiatans()->attach($april);

    $awal = NilaiAwalKuitansi::untuk($mei);

    expect($awal['untuk_pembayaran'])->toBe('Honor GBPNS Mei 2026 a/n Abdul Hamid dkk')
        ->and($awal['penerima'])->toBe('Abdul Hamid')
        ->and($awal['nomor'])->toBe('002/05/SMPG/2026');
});

test('rentang bulan diganti dan kata mirip bulan tidak disentuh', function () {
    $sekolah = sekolahSmp();
    $maret = kegiatanGaji($sekolah, 3);
    $april = kegiatanGaji($sekolah, 4);
    $kuitansi = Kuitansi::factory()->create([
        'sekolah_id' => $sekolah->id, 'bulan' => 3, 'nomor_urut' => 1, 'nomor' => '001/03/SMPG/2026',
        'untuk_pembayaran' => 'Honor Agustina Januari-Maret 2026',
    ]);
    $kuitansi->kegiatans()->attach($maret);

    expect(NilaiAwalKuitansi::untuk($april)['untuk_pembayaran'])->toBe('Honor Agustina April 2026');

    $rapel = NilaiAwalKuitansi::untuk($april, [$maret]);
    expect($rapel['untuk_pembayaran'])->toBe('Honor Agustina Maret-April 2026');
});

test('tahun lama diganti tahun tujuan hanya sebagai angka utuh', function () {
    $sekolah = sekolahSmp();
    $lama = kegiatanGaji($sekolah, 12, ['tahun' => 2025]);
    $baru = kegiatanGaji($sekolah, 1);
    $kuitansi = Kuitansi::factory()->create([
        'sekolah_id' => $sekolah->id, 'tahun' => 2025, 'bulan' => 12, 'nomor_urut' => 1, 'nomor' => '001/12/SMPG/2025',
        'untuk_pembayaran' => 'Honor Desember 2025 no 20250',
    ]);
    $kuitansi->kegiatans()->attach($lama);

    expect(NilaiAwalKuitansi::untuk($baru)['untuk_pembayaran'])->toBe('Honor Januari 2026 no 20250');
});

test('kuitansi tidak menyalin teks dari kegiatan lain', function () {
    $sekolah = sekolahSmp();
    $listrik = kegiatanGaji($sekolah, 4, ['uraian' => 'Listrik', 'nama' => 'Listrik', 'kode' => '5.1.02.01.01.0099']);
    $gaji = kegiatanGaji($sekolah, 5);
    Kuitansi::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => 4, 'untuk_pembayaran' => 'Bayar listrik April 2026'])
        ->kegiatans()->attach($listrik);

    expect(NilaiAwalKuitansi::untuk($gaji)['untuk_pembayaran'])->toBe('Gaji/Honor Rutin PTK/Operator Mei 2026');
});

test('rapel Januari sampai Maret menjumlah dan menandai bulan awal dirapel', function () {
    $sekolah = sekolahSmp();
    $bulan = [1 => kegiatanGaji($sekolah, 1), 2 => kegiatanGaji($sekolah, 2), 3 => kegiatanGaji($sekolah, 3)];

    $awal = NilaiAwalKuitansi::untuk($bulan[3], [$bulan[1], $bulan[2]]);
    expect($awal['jumlah'])->toBe(47_880_000)
        ->and($awal['untuk_pembayaran'])->toBe('Gaji/Honor Rutin PTK/Operator Januari-Maret 2026')
        ->and($awal['nomor'])->toBe('001/03/SMPG/2026');

    $this->post(route('kuitansi.store', $bulan[3]), isianKuitansi([
        'nomor' => $awal['nomor'], 'jumlah' => $awal['jumlah'], 'tanggal_lunas' => '2026-03-31',
        'kegiatan_rapel_ids' => [$bulan[1]->id, $bulan[2]->id],
    ]))->assertSessionHasNoErrors();

    $kuitansi = Kuitansi::firstOrFail();
    expect($kuitansi->jumlah)->toBe(47_880_000)->and($kuitansi->bulan)->toBe(3)
        ->and($kuitansi->kegiatans()->count())->toBe(3);

    $this->get(route('kegiatan.show', $bulan[1]))
        ->assertInertia(fn (Assert $page) => $page->where('kegiatan.status', 'dirapel')->where('kegiatan.rapel.bulan', 3));
    $this->get(route('kegiatan.show', $bulan[2]))
        ->assertInertia(fn (Assert $page) => $page->where('kegiatan.status', 'dirapel'));
});

test('bulan kuitansi rapel adalah bulan terbesar walau dibuat dari bulan awal', function () {
    $sekolah = sekolahSmp();
    $januari = kegiatanGaji($sekolah, 1);
    $maret = kegiatanGaji($sekolah, 3);

    $this->post(route('kuitansi.store', $januari), isianKuitansi([
        'nomor' => '001/03/SMPG/2026', 'kegiatan_rapel_ids' => [$maret->id],
    ]))->assertSessionHasNoErrors();

    expect(Kuitansi::firstOrFail()->bulan)->toBe(3);
});

test('rapel yang tidak sah ditolak', function (string $kasus) {
    $sekolah = sekolahSmp();
    $maret = kegiatanGaji($sekolah, 3);
    $lain = match ($kasus) {
        'beda uraian' => kegiatanGaji($sekolah, 2, ['uraian' => 'Listrik', 'nama' => 'Listrik']),
        'beda kode' => kegiatanGaji($sekolah, 2, ['kode' => '5.1.02.01.01.0001']),
        'beda kode program' => kegiatanGaji($sekolah, 2, ['kode_program' => '09.09']),
        'beda tahun' => kegiatanGaji($sekolah, 2, ['tahun' => 2025]),
        'beda tahap' => kegiatanGaji($sekolah, 2, ['tahap' => 2]),
        'beda sekolah' => kegiatanGaji(sekolahSmp(['kode_bukti' => 'SMPY']), 2),
        'status manual' => kegiatanGaji($sekolah, 2, ['status_manual' => 'di_luar_aplikasi']),
        'hilang dari anggaran' => kegiatanGaji($sekolah, 2, ['hilang_dari_anggaran' => true]),
        'anggota acara' => kegiatanGaji($sekolah, 2, ['acara_id' => Acara::factory()->create(['sekolah_id' => $sekolah->id])->id]),
        'sudah berkuitansi' => tap(kegiatanGaji($sekolah, 2), function (Kegiatan $k) use ($sekolah): void {
            Kuitansi::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => 2, 'nomor_urut' => 9, 'nomor' => '009/02/SMPG/2026'])->kegiatans()->attach($k);
        }),
    };

    $this->post(route('kuitansi.store', $maret), isianKuitansi([
        'nomor' => '001/03/SMPG/2026', 'tanggal_lunas' => '2026-03-31', 'kegiatan_rapel_ids' => [$lain->id],
    ]))->assertSessionHasErrors('kegiatan_rapel_ids');

    expect($maret->kuitansi()->count())->toBe(0)->and($lain->fresh()->kuitansi()->count())->toBe($kasus === 'sudah berkuitansi' ? 1 : 0);
})->with(['beda uraian', 'beda kode', 'beda kode program', 'beda tahun', 'beda tahap', 'beda sekolah', 'status manual', 'hilang dari anggaran', 'anggota acara', 'sudah berkuitansi']);

test('kegiatan yang sudah berkuitansi tidak bisa dibuatkan lagi', function () {
    $januari = kegiatanGaji(sekolahSmp(), 1);

    $this->post(route('kuitansi.store', $januari), isianKuitansi())->assertSessionHasNoErrors();
    $this->post(route('kuitansi.store', $januari), isianKuitansi(['nomor' => '002/01/SMPG/2026']))
        ->assertSessionHasErrors('kegiatan');

    expect(Kuitansi::count())->toBe(1);
});

test('anggota acara boleh punya kuitansi sendiri', function () {
    $sekolah = sekolahSmp();
    $acara = Acara::factory()->create(['sekolah_id' => $sekolah->id]);
    $baris = kegiatanGaji($sekolah, 1, ['acara_id' => $acara->id]);

    $this->post(route('kuitansi.store', $baris), isianKuitansi())->assertSessionHasNoErrors();
    expect(Kuitansi::count())->toBe(1);
});

test('tanggal lunas harus di tahun anggaran', function () {
    $januari = kegiatanGaji(sekolahSmp(), 1);

    $this->post(route('kuitansi.store', $januari), isianKuitansi(['tanggal_lunas' => '2025-12-31']))
        ->assertSessionHasErrors('tanggal_lunas');
    $this->post(route('kuitansi.store', $januari), isianKuitansi(['tanggal_lunas' => '2027-01-01']))
        ->assertSessionHasErrors('tanggal_lunas');

    expect(Kuitansi::count())->toBe(0);

    $this->post(route('kuitansi.store', $januari), isianKuitansi(['tanggal_lunas' => '2026-12-31']))
        ->assertSessionHasNoErrors();
});

test('isian kuitansi divalidasi', function (array $ubah, string $kolom) {
    $januari = kegiatanGaji(sekolahSmp(), 1);

    $this->post(route('kuitansi.store', $januari), isianKuitansi($ubah))->assertSessionHasErrors($kolom);
})->with([
    'jumlah nol' => [['jumlah' => 0], 'jumlah'],
    'jumlah bukan angka' => [['jumlah' => 'abc'], 'jumlah'],
    'untuk pembayaran 201 karakter' => [['untuk_pembayaran' => str_repeat('a', 201)], 'untuk_pembayaran'],
    'penerima kosong' => [['penerima' => ''], 'penerima'],
    'nomor tanpa angka di depan' => [['nomor' => 'ABC/01'], 'nomor'],
    'tanggal rusak' => [['tanggal_lunas' => 'kemarin'], 'tanggal_lunas'],
]);

test('untuk pembayaran 200 karakter diterima', function () {
    $januari = kegiatanGaji(sekolahSmp(), 1);

    $this->post(route('kuitansi.store', $januari), isianKuitansi(['untuk_pembayaran' => str_repeat('a', 200)]))
        ->assertSessionHasNoErrors();
});

test('ubah hanya mengubah isian dan cakupan tetap', function () {
    $sekolah = sekolahSmp();
    $januari = kegiatanGaji($sekolah, 1);
    $februari = kegiatanGaji($sekolah, 2);
    $this->post(route('kuitansi.store', $februari), isianKuitansi([
        'nomor' => '001/02/SMPG/2026', 'kegiatan_rapel_ids' => [$januari->id], 'tanggal_lunas' => '2026-02-20',
    ]))->assertSessionHasNoErrors();
    $kuitansi = Kuitansi::firstOrFail();

    $this->patch(route('kuitansi.update', $kuitansi), isianKuitansi([
        'nomor' => '007/02/SMPG/2026', 'penerima' => 'Siti', 'jumlah' => 31_000_000, 'untuk_pembayaran' => 'Honor baru',
        'tanggal_lunas' => '2026-02-25', 'bulan' => 9, 'kegiatan_rapel_ids' => [], 'sekolah_id' => 999,
    ]))->assertSessionHasNoErrors();

    $kuitansi->refresh();
    expect($kuitansi->nomor)->toBe('007/02/SMPG/2026')->and($kuitansi->nomor_urut)->toBe(7)
        ->and($kuitansi->penerima)->toBe('Siti')->and($kuitansi->jumlah)->toBe(31_000_000)
        ->and($kuitansi->untuk_pembayaran)->toBe('Honor baru')
        ->and($kuitansi->tanggal_lunas->toDateString())->toBe('2026-02-25')
        ->and($kuitansi->bulan)->toBe(2)->and($kuitansi->sekolah_id)->toBe($sekolah->id)
        ->and($kuitansi->kegiatans()->count())->toBe(2);
});

test('tanggal lunas saat ubah juga harus di tahun kuitansi', function () {
    $this->post(route('kuitansi.store', kegiatanGaji(sekolahSmp(), 1)), isianKuitansi())->assertSessionHasNoErrors();

    $this->patch(route('kuitansi.update', Kuitansi::firstOrFail()), isianKuitansi(['tanggal_lunas' => '2025-01-01']))
        ->assertSessionHasErrors('tanggal_lunas');
});

test('hapus kuitansi melepas kegiatan dan membiarkan foto', function () {
    $sekolah = sekolahSmp();
    $januari = kegiatanGaji($sekolah, 1);
    $februari = kegiatanGaji($sekolah, 2);
    $this->post(route('kuitansi.store', $februari), isianKuitansi([
        'nomor' => '001/02/SMPG/2026', 'kegiatan_rapel_ids' => [$januari->id], 'tanggal_lunas' => '2026-02-20',
    ]))->assertSessionHasNoErrors();
    $foto = Foto::factory()->create(['pemilik_type' => $januari->getMorphClass(), 'pemilik_id' => $januari->id]);

    $this->delete(route('kuitansi.destroy', Kuitansi::firstOrFail()))->assertRedirect();

    expect(Kuitansi::count())->toBe(0)
        ->and(DB::table('kegiatan_kuitansi')->count())->toBe(0)
        ->and(Foto::whereKey($foto->id)->exists())->toBeTrue();
});

test('unggah ulang anggaran tidak mengubah kuitansi', function () {
    $this->seed(DataAwalSeeder::class);
    $anggaran = (new PdfAnggaranParser)->parseToken(tokenFixture('arkas-maret-2026'));
    app(SimpanAnggaran::class)->simpan($anggaran, 'maret.pdf');

    $kegiatan = Kegiatan::query()->where('bulan', 3)->orderBy('id')->firstOrFail();
    $this->post(route('kuitansi.store', $kegiatan), [
        'nomor' => NilaiAwalKuitansi::untuk($kegiatan)['nomor'],
        'untuk_pembayaran' => 'Teks tulisan pemilik',
        'penerima' => 'Pemilik',
        'tanggal_lunas' => '2026-03-15',
        'jumlah' => 123_456,
    ])->assertSessionHasNoErrors();

    $kegiatan->update(['jumlah' => 1, 'nama' => 'Dirusak']);
    app(SimpanAnggaran::class)->simpan($anggaran, 'maret.pdf');

    $kuitansi = Kuitansi::firstOrFail();
    expect($kuitansi->jumlah)->toBe(123_456)->and($kuitansi->untuk_pembayaran)->toBe('Teks tulisan pemilik')
        ->and($kegiatan->fresh()->jumlah)->not->toBe(1);
});

test('halaman kegiatan membawa nilai awal, kuitansi, dan kandidat rapel', function () {
    $sekolah = sekolahSmp();
    $maret = kegiatanGaji($sekolah, 3);
    $januari = kegiatanGaji($sekolah, 1);
    $mei = kegiatanGaji($sekolah, 5);
    kegiatanGaji($sekolah, 2, ['uraian' => 'Listrik', 'nama' => 'Listrik']);
    kegiatanGaji($sekolah, 4, ['status_manual' => 'di_luar_aplikasi']);
    kegiatanGaji($sekolah, 7, ['hilang_dari_anggaran' => true]);
    kegiatanGaji($sekolah, 6, ['acara_id' => Acara::factory()->create(['sekolah_id' => $sekolah->id])->id]);

    $this->get(route('kegiatan.show', $maret))->assertInertia(fn (Assert $page) => $page
        ->where('kuitansi', null)
        ->where('nilaiAwal.nomor', '001/03/SMPG/2026')
        ->where('nilaiAwal.jumlah', 15_960_000)
        ->where('nilaiAwal.terbilang', 'Lima belas juta sembilan ratus enam puluh ribu rupiah')
        ->has('kandidatRapel', 2)
        ->where('kandidatRapel.0.id', $januari->id)
        ->where('kandidatRapel.1.id', $mei->id)
        ->where('rapelDipilih', []));

    $this->get(route('kegiatan.show', ['kegiatan' => $maret, 'rapel' => [$januari->id, $mei->id]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nilaiAwal.jumlah', 47_880_000)
            ->where('nilaiAwal.untuk_pembayaran', 'Gaji/Honor Rutin PTK/Operator Januari, Maret, Mei 2026')
            ->where('rapelDipilih', [$januari->id, $mei->id])
            ->where('nilaiAwal.nomor', '001/05/SMPG/2026'));

    $this->post(route('kuitansi.store', $maret), isianKuitansi(['nomor' => '001/03/SMPG/2026', 'tanggal_lunas' => '2026-03-20']))
        ->assertSessionHasNoErrors();

    $this->get(route('kegiatan.show', $maret))->assertInertia(fn (Assert $page) => $page
        ->where('nilaiAwal', null)
        ->where('kuitansi.nomor', '001/03/SMPG/2026')
        ->where('kuitansi.terbilang', 'Lima belas juta sembilan ratus enam puluh ribu rupiah')
        ->where('kuitansi.tanggal_lunas', '2026-03-20')
        ->where('kuitansi.bulanCakupan', [3])
        ->has('kandidatRapel', 0));
});

test('rute kuitansi memerlukan login', function () {
    auth()->logout();
    $kegiatan = kegiatanGaji(sekolahSmp(), 1);

    $this->post(route('kuitansi.store', $kegiatan), isianKuitansi())->assertRedirect(route('login'));
    $this->patch(route('kuitansi.update', Kuitansi::factory()->create()), isianKuitansi())->assertRedirect(route('login'));
    $this->delete(route('kuitansi.destroy', Kuitansi::factory()->create()))->assertRedirect(route('login'));
});

test('kegiatan dengan status manual tidak dibuatkan kuitansi', function () {
    $kegiatan = kegiatanGaji(sekolahSmp(), 1, ['status_manual' => 'di_luar_aplikasi']);

    $this->post(route('kuitansi.store', $kegiatan), isianKuitansi())->assertSessionHasErrors('kegiatan');

    expect(Kuitansi::count())->toBe(0);
});

test('nomor dengan lebih dari sembilan digit di depan ditolak sebagai galat validasi', function () {
    $januari = kegiatanGaji(sekolahSmp(), 1);

    $this->post(route('kuitansi.store', $januari), isianKuitansi(['nomor' => '1234567890/01/SMPG/2026']))
        ->assertSessionHasErrors('nomor');
    $this->post(route('kuitansi.store', $januari), isianKuitansi(['nomor' => '123456789/01/SMPG/2026']))
        ->assertSessionHasNoErrors();

    $lain = kegiatanGaji(sekolahSmp(['kode_bukti' => 'SMPX']), 1);
    expect(fn () => app(SimpanKuitansi::class)->buat([$lain->id], isianKuitansi(['nomor' => '1234567890/x'])))
        ->toThrow(ValidationException::class);
});

dataset('teks lama', [
    'huruf besar' => ['HONOR JANUARI 2026', 'HONOR Mei 2026'],
    'huruf kecil' => ['honor januari 2026', 'honor Mei 2026'],
    'sampai' => ['Honor Januari sampai Maret 2026', 'Honor Mei 2026'],
    'hingga' => ['Honor Januari hingga Maret 2026', 'Honor Mei 2026'],
    's/d' => ['Honor Januari s/d Maret 2026', 'Honor Mei 2026'],
    's.d.' => ['Honor Januari s.d. Maret 2026', 'Honor Mei 2026'],
    'dan' => ['Honor Januari dan Februari 2026', 'Honor Mei 2026'],
    'em dash' => ['Honor Januari—Maret 2026', 'Honor Mei 2026'],
    'en dash' => ['Honor Januari – Maret 2026', 'Honor Mei 2026'],
    'hubung' => ['Honor Januari-Maret 2026', 'Honor Mei 2026'],
    'daftar' => ['Honor Januari, Maret 2026', 'Honor Mei 2026'],
    'koma biasa tetap' => ['Honor Januari 2026, guru tetap', 'Honor Mei 2026, guru tetap'],
]);

test('nama bulan lama diganti dengan berbagai gaya penulisan', function (string $lama, string $baru) {
    $sekolah = sekolahSmp();
    $mei = kegiatanGaji($sekolah, 5);
    Kuitansi::factory()->create(['sekolah_id' => $sekolah->id, 'bulan' => 4, 'untuk_pembayaran' => $lama])
        ->kegiatans()->attach(kegiatanGaji($sekolah, 4));

    expect(NilaiAwalKuitansi::untuk($mei)['untuk_pembayaran'])->toBe($baru);
})->with('teks lama');

test('bulan dan tahun hanya diganti sebelum a/n sehingga nama penerima aman', function () {
    $sekolah = sekolahSmp();
    $mei = kegiatanGaji($sekolah, 5);
    $lama = kegiatanGaji($sekolah, 4, ['tahun' => 2025]);
    Kuitansi::factory()->create(['sekolah_id' => $sekolah->id, 'tahun' => 2025, 'bulan' => 4, 'nomor' => '001/04/SMPG/2025', 'nomor_urut' => 1,
        'untuk_pembayaran' => 'Honor April 2025 a/n Juni Astuti dkk Maret 2025'])->kegiatans()->attach($lama);

    expect(NilaiAwalKuitansi::untuk($mei)['untuk_pembayaran'])->toBe('Honor Mei 2026 a/n Juni Astuti dkk Maret 2025');

    Kuitansi::query()->update(['untuk_pembayaran' => 'Honor April 2025 A/N Juni Astuti']);
    expect(NilaiAwalKuitansi::untuk($mei)['untuk_pembayaran'])->toBe('Honor Mei 2026 A/N Juni Astuti');
});

test('rapel bulan tidak berurutan menyebut setiap bulan', function () {
    $sekolah = sekolahSmp();
    $bulan = collect([1, 2, 3, 5])->mapWithKeys(fn (int $b) => [$b => kegiatanGaji($sekolah, $b)]);
    $dasar = 'Gaji/Honor Rutin PTK/Operator';

    expect(NilaiAwalKuitansi::untuk($bulan[1], [$bulan[3]])['untuk_pembayaran'])->toBe("$dasar Januari, Maret 2026")
        ->and(NilaiAwalKuitansi::untuk($bulan[1], [$bulan[2], $bulan[3]])['untuk_pembayaran'])->toBe("$dasar Januari-Maret 2026")
        ->and(NilaiAwalKuitansi::untuk($bulan[1], [$bulan[2], $bulan[5]])['untuk_pembayaran'])->toBe("$dasar Januari, Februari, Mei 2026");
});

test('hapus kegiatan hilang ikut menghapus kuitansi yang hanya mencakup dia', function () {
    $kegiatan = kegiatanGaji(sekolahSmp(), 1, ['hilang_dari_anggaran' => true]);
    $this->post(route('kuitansi.store', $kegiatan), isianKuitansi())->assertSessionHasNoErrors();

    $this->delete(route('kegiatan.destroy', $kegiatan))->assertRedirect(route('dashboard', ['bulan' => '2026-01']));

    expect(Kuitansi::count())->toBe(0)->and(DB::table('kegiatan_kuitansi')->count())->toBe(0)->and(Kegiatan::count())->toBe(0);
});

test('hapus kegiatan yang ada di kuitansi rapel ditolak', function () {
    $sekolah = sekolahSmp();
    $januari = kegiatanGaji($sekolah, 1);
    $februari = kegiatanGaji($sekolah, 2);
    $this->post(route('kuitansi.store', $februari), isianKuitansi([
        'nomor' => '001/02/SMPG/2026', 'kegiatan_rapel_ids' => [$januari->id], 'tanggal_lunas' => '2026-02-20',
    ]))->assertSessionHasNoErrors();
    $januari->update(['hilang_dari_anggaran' => true]);

    $this->delete(route('kegiatan.destroy', $januari))->assertStatus(422);

    expect(Kuitansi::count())->toBe(1)->and(Kegiatan::whereKey($januari->id)->exists())->toBeTrue();
});

test('membatasi nomor dan penerima agar muat di kuitansi', function (string $kolom, string $sah, string $kepanjangan, string $pesan) {
    $kegiatan = kegiatanGaji(sekolahSmp(), 1);

    $this->post(route('kuitansi.store', $kegiatan), isianKuitansi([$kolom => $kepanjangan]))
        ->assertSessionHasErrors([$kolom => $pesan]);
    $this->post(route('kuitansi.store', $kegiatan), isianKuitansi([$kolom => $sah]))
        ->assertSessionHasNoErrors();

    expect(Kuitansi::firstOrFail()->{$kolom})->toBe($sah);
})->with([
    'nomor' => ['nomor', '1/'.str_repeat('W', 38), '1/'.str_repeat('W', 39), 'Nomor bukti maksimal 40 karakter agar muat di kuitansi.'],
    'penerima' => ['penerima', str_repeat('W', 90), str_repeat('W', 91), 'Nama penerima maksimal 90 karakter agar muat di kuitansi.'],
]);
