<?php

use App\Enums\SistemAnggaran;
use App\Enums\StatusManual;
use App\Impor\AnggaranTerbaca;
use App\Impor\BarisAnggaran;
use App\Impor\ImporDitolak;
use App\Impor\PdfAnggaranParser;
use App\Impor\PenyaranJenis;
use App\Impor\SimpanAnggaran;
use App\Models\Acara;
use App\Models\Foto;
use App\Models\ImporAnggaran;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(DataAwalSeeder::class);
    Storage::fake('local');

    // Parser palsu: isi PDF unggahan hanya menyebut nama fixture token.
    app()->bind(PdfAnggaranParser::class, fn () => new class extends PdfAnggaranParser
    {
        public function parse(string $path): AnggaranTerbaca
        {
            $isi = (string) file_get_contents($path);

            if (str_contains($isi, 'rusak')) {
                throw new ImporDitolak('PDF ini rusak.');
            }

            preg_match('/fixture=(\S+)/', $isi, $m);

            return $this->parseToken(tokenFixture($m[1]));
        }
    });
});

function arkasMaret(): AnggaranTerbaca
{
    return (new PdfAnggaranParser)->parseToken(tokenFixture('arkas-maret-2026'));
}

function erkam(): AnggaranTerbaca
{
    return (new PdfAnggaranParser)->parseToken(tokenFixture('erkam-2026'));
}

function simpanAnggaran(): SimpanAnggaran
{
    return app(SimpanAnggaran::class);
}

function pdfPalsu(string $fixture, string $nama = 'a.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nama, "%PDF-1.4\nfixture={$fixture}\n");
}

/**
 * @param  array<string, mixed>  $ubah
 */
function denganUbahan(AnggaranTerbaca $a, array $ubah): AnggaranTerbaca
{
    return new AnggaranTerbaca(
        $ubah['sistem'] ?? $a->sistem,
        $ubah['identitas'] ?? $a->identitas,
        $a->tahun,
        $a->bulan,
        $ubah['sumberDana'] ?? $a->sumberDana,
        $ubah['baris'] ?? $a->baris,
        $a->totalDokumen,
    );
}

it('menyimpan ARKAS Maret sebagai sepuluh kegiatan bulan 3', function () {
    $ringkasan = simpanAnggaran()->simpan(arkasMaret(), 'maret.pdf');

    $smp = Sekolah::where('identitas', '20606714')->firstOrFail();
    expect(Kegiatan::count())->toBe(10)
        ->and(Kegiatan::where('bulan', 3)->where('periode_impor', 3)->count())->toBe(10)
        ->and(Kegiatan::where('jenis_dikonfirmasi', true)->count())->toBe(0)
        ->and($ringkasan->baru)->toBe(10)->and($ringkasan->berubah)->toBe(0)->and($ringkasan->hilang)->toBe(0)
        ->and($ringkasan->total)->toBe(19224000)->and($ringkasan->sekolah->is($smp))->toBeTrue();

    $impor = ImporAnggaran::firstOrFail();
    expect($impor->periode)->toBe(3)->and($impor->tahun)->toBe(2026)->and($impor->total)->toBe(19224000)
        ->and($impor->nama_file)->toBe('maret.pdf');

    $bola = Kegiatan::where('uraian', 'like', 'Bola Volley%')->firstOrFail();
    expect($bola->nama)->toBe('Bola Volley')->and($bola->kode_program)->toBe('05.08.04')->and($bola->pajak)->toBe(0);
    expect(Kegiatan::where('uraian', 'like', 'Laptop%')->firstOrFail()->nama)->toBe('Laptop/ komputer');
});

it('memecah rutin ERKAM per bulan dan membiarkan sekali belanja tanpa bulan', function () {
    $ringkasan = simpanAnggaran()->simpan(erkam(), 'rkam.pdf');

    $gaji1 = Kegiatan::where('tahap', 1)->where('uraian', 'Gaji/Honor Rutin PTK/Operator')->orderBy('bulan')->get();
    $gaji2 = Kegiatan::where('tahap', 2)->where('uraian', 'Gaji/Honor Rutin PTK/Operator')->orderBy('bulan')->get();

    expect($gaji1->pluck('bulan')->all())->toBe([1, 2, 3, 4, 5, 6])
        ->and($gaji2->pluck('bulan')->all())->toBe([7, 8, 9, 10, 11, 12])
        ->and($gaji1->pluck('jumlah')->unique()->all())->toBe([15960000])
        ->and($gaji1->pluck('harga')->unique()->all())->toBe([15960000])
        ->and($gaji1->pluck('periode_impor')->unique()->all())->toBe([0])
        ->and($gaji1->pluck('kunci_impor')->unique())->toHaveCount(6)
        ->and($gaji1->first()->nama)->toBe('Gaji/Honor Rutin PTK/Operator');

    // 14 baris Tahap I = 4 rutin + 10 sekali belanja; 10 baris Tahap II = 4 rutin + 6 sekali belanja.
    expect(Kegiatan::where('tahap', 1)->whereNull('bulan')->count())->toBe(10)
        ->and(Kegiatan::where('tahap', 2)->whereNull('bulan')->count())->toBe(6)
        ->and(Kegiatan::where('tahap', 1)->whereNotNull('bulan')->count())->toBe(24)
        ->and($ringkasan->jumlahKegiatan)->toBe(Kegiatan::count())
        ->and($ringkasan->total)->toBe(320150000)
        ->and(ImporAnggaran::firstOrFail()->periode)->toBe(0);

    $sekali = Kegiatan::where('uraian', 'Biaya -Cetak dan Penggandaan Lainnya | Pembelian Naskah Soal')->firstOrFail();
    expect($sekali->nama)->toBe('Biaya -Cetak dan Penggandaan Lainnya');
});

it('menyimpan dua Jendela Aluminium/Kayu yang kembar sebagai dua baris', function () {
    simpanAnggaran()->simpan(erkam(), 'rkam.pdf');

    $jendela = Kegiatan::where('uraian', 'Jendela Aluminium/Kayu')->orderBy('harga')->get();

    expect($jendela)->toHaveCount(2)
        ->and($jendela->pluck('harga')->all())->toBe([4890000, 10500000])
        ->and($jendela->pluck('kunci_impor')->unique())->toHaveCount(2);
});

it('mempertahankan bulan pilihan, jenis, dan foto saat unggah ulang', function () {
    simpanAnggaran()->simpan(erkam(), 'rkam.pdf');

    $pagar = Kegiatan::where('uraian', 'Pagar Besi')->firstOrFail();
    $lain = JenisKegiatan::where('nama', 'Rapat/Pelatihan')->firstOrFail();
    $pagar->update(['bulan' => 8, 'jenis_kegiatan_id' => $lain->id, 'jenis_dikonfirmasi' => true]);
    $foto = Foto::factory()->create(['pemilik_type' => $pagar->getMorphClass(), 'pemilik_id' => $pagar->id]);

    $ubah = array_map(
        fn ($b) => $b->uraian === 'Pagar Besi'
            ? new BarisAnggaran($b->kode, $b->kodeProgram, $b->uraian, $b->koefisien, $b->harga, $b->pajak, $b->jumlah + 1, $b->tahap)
            : $b,
        erkam()->baris,
    );
    $ringkasan = simpanAnggaran()->simpan(denganUbahan(erkam(), ['baris' => $ubah]), 'rkam2.pdf');

    $pagar->refresh();
    expect($ringkasan->berubah)->toBe(1)->and($ringkasan->baru)->toBe(0)
        ->and($pagar->jumlah)->toBe(15770001)
        ->and($pagar->bulan)->toBe(8)
        ->and($pagar->jenis_kegiatan_id)->toBe($lain->id)
        ->and($pagar->jenis_dikonfirmasi)->toBeTrue()
        ->and($pagar->fotos)->toHaveCount(1)
        ->and($foto->fresh())->not->toBeNull()
        ->and(ImporAnggaran::count())->toBe(1)
        ->and(ImporAnggaran::firstOrFail()->nama_file)->toBe('rkam2.pdf');
});

it('mengunggah ulang PDF yang sama menghasilkan baru 0 dan berubah 0', function () {
    simpanAnggaran()->simpan(arkasMaret(), 'maret.pdf');
    $jumlah = Kegiatan::count();

    $pratinjau = simpanAnggaran()->bandingkan(arkasMaret());
    $ulang = simpanAnggaran()->simpan(arkasMaret(), 'maret.pdf');

    expect($pratinjau->baru)->toBe(0)->and($pratinjau->berubah)->toBe(0)->and($pratinjau->hilang)->toBe(0)
        ->and($ulang->baru)->toBe(0)->and($ulang->berubah)->toBe(0)
        ->and(Kegiatan::count())->toBe($jumlah)
        ->and(ImporAnggaran::count())->toBe(1);
});

it('menghapus kegiatan hilang tanpa berkas dan menandai yang berkas', function () {
    simpanAnggaran()->simpan(arkasMaret(), 'maret.pdf');

    $semua = Kegiatan::orderBy('id')->get();
    [$denganFoto, $denganStatus, $denganKuitansi, $denganAcara, $polos] = [$semua[0], $semua[1], $semua[2], $semua[3], $semua[4]];

    Foto::factory()->create(['pemilik_type' => $denganFoto->getMorphClass(), 'pemilik_id' => $denganFoto->id]);
    $denganStatus->update(['status_manual' => StatusManual::TidakDilaksanakan]);
    $denganKuitansi->kuitansi()->attach(Kuitansi::factory()->create(['sekolah_id' => $denganKuitansi->sekolah_id]));
    $denganAcara->update(['acara_id' => Acara::factory()->create(['sekolah_id' => $denganAcara->sekolah_id])->id]);

    $sisa = array_values(array_filter(arkasMaret()->baris, fn ($b, $i) => $i >= 5, ARRAY_FILTER_USE_BOTH));
    $dokumen = denganUbahan(arkasMaret(), ['baris' => $sisa]);

    expect(simpanAnggaran()->bandingkan($dokumen)->hilang)->toBe(5)
        ->and(Kegiatan::count())->toBe(10);

    $ringkasan = simpanAnggaran()->simpan($dokumen, 'maret.pdf');

    expect($ringkasan->hilang)->toBe(5)
        ->and(Kegiatan::count())->toBe(9)
        ->and(Kegiatan::find($polos->id))->toBeNull()
        ->and(Kegiatan::where('hilang_dari_anggaran', true)->pluck('id')->sort()->values()->all())
        ->toBe([$denganFoto->id, $denganStatus->id, $denganKuitansi->id, $denganAcara->id]);

    // Muncul lagi di PDF berikutnya: bendera hilang dicabut dan dihitung berubah.
    $kembali = simpanAnggaran()->simpan(arkasMaret(), 'maret.pdf');
    expect($kembali->berubah)->toBe(4)->and($kembali->baru)->toBe(1)
        ->and(Kegiatan::where('hilang_dari_anggaran', true)->count())->toBe(0);
});

it('menolak identitas tidak dikenal, sistem tidak sesuai, dan sumber dana lain', function (array $ubah, string $pesan) {
    expect(fn () => simpanAnggaran()->bandingkan(denganUbahan(arkasMaret(), $ubah)))
        ->toThrow(ImporDitolak::class, $pesan);
    expect(fn () => simpanAnggaran()->simpan(denganUbahan(arkasMaret(), $ubah), 'a.pdf'))
        ->toThrow(ImporDitolak::class, $pesan);

    expect(Kegiatan::count())->toBe(0)->and(ImporAnggaran::count())->toBe(0);
})->with([
    'identitas' => [['identitas' => '99999999'], '99999999'],
    'sistem' => [['sistem' => SistemAnggaran::Erkam], 'ERKAM'],
    'sumber dana' => [['sumberDana' => 'BOSP Kinerja'], 'BOSP Kinerja'],
]);

it('menolak rutin ERKAM yang jumlahnya tidak cocok atau melewati akhir tahap', function (string $koefisien, int $jumlah, string $pesan) {
    $baris = array_map(
        fn ($b) => $b->tahap === 2 && $b->uraian === 'Listrik Pascabayar'
            ? new BarisAnggaran($b->kode, $b->kodeProgram, $b->uraian, $koefisien, $b->harga, $b->pajak, $jumlah, $b->tahap)
            : $b,
        erkam()->baris,
    );

    expect(fn () => simpanAnggaran()->bandingkan(denganUbahan(erkam(), ['baris' => $baris])))
        ->toThrow(ImporDitolak::class, $pesan);
})->with([
    'jumlah tidak cocok' => ['6 bulan', 5999999, 'tidak sama dengan jumlah'],
    'melewati akhir tahap' => ['7 bulan', 7000000, 'melewati bulan terakhir'],
]);

it('menyaran jenis dari kata utuh tanpa peka huruf', function (string $uraian, string $jenis) {
    expect((new PenyaranJenis)->untuk($uraian)->nama)->toBe($jenis);
})->with([
    ['Bola Volley-Bola Volley', 'Belanja barang'],
    ['Jamuan Makan-Tipe : Jamuan', 'Rapat/Pelatihan'],
    ['Biaya Pembayaran Listrik-Tipe : Jasa Kantor', 'Langganan'],
    ['LISTRIK pascabayar', 'Langganan'],
    ['Gaji Petugas Kebersihan', 'Gaji/Honor'],
    ['Bolak-balik ke bank', 'Lainnya'],
    ['Sesuatu yang tak dikenal', 'Lainnya'],
]);

it('menyaran jenis saat impor tetapi tidak menimpanya saat unggah ulang', function () {
    simpanAnggaran()->simpan(arkasMaret(), 'maret.pdf');

    expect(Kegiatan::where('uraian', 'like', 'Bola Volley%')->firstOrFail()->jenisKegiatan->nama)->toBe('Belanja barang')
        ->and(Kegiatan::where('uraian', 'like', 'Jamuan Makan%')->firstOrFail()->jenisKegiatan->nama)->toBe('Rapat/Pelatihan')
        ->and(Kegiatan::where('uraian', 'like', '%Listrik%')->firstOrFail()->jenisKegiatan->nama)->toBe('Langganan');
});

it('tamu ditolak', function () {
    $this->get(route('impor.index'))->assertRedirect('/login');
    $this->post(route('impor.pratinjau'))->assertRedirect('/login');
    $this->post(route('impor.simpan'))->assertRedirect('/login');
});

it('menampilkan layar impor', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('impor.index'))
        ->assertInertia(fn ($page) => $page->component('impor/Index'));
});

it('pratinjau tidak menyimpan apa pun dan menyiapkan token', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('impor.pratinjau'), ['files' => [pdfPalsu('arkas-maret-2026', 'maret.pdf'), pdfPalsu('erkam-2026', 'rkam.pdf')]])
        ->assertRedirect(route('impor.index'))
        ->assertSessionHas('pratinjau', function (array $daftar) {
            return count($daftar) === 2
                && $daftar[0]['nama_file'] === 'maret.pdf'
                && $daftar[0]['ringkasan']['baru'] === 10
                && $daftar[0]['ringkasan']['sekolah'] === 'SMP Garuda'
                && $daftar[0]['ringkasan']['bulan'] === 3
                && Str::isUuid($daftar[0]['token'])
                && $daftar[1]['ringkasan']['jumlah_kegiatan'] === 64;
        });

    expect(Kegiatan::count())->toBe(0)->and(ImporAnggaran::count())->toBe(0)
        ->and(Storage::disk('local')->files('impor-sementara'))->toHaveCount(2);
});

it('pratinjau menolak file bermasalah per file dan melanjutkan yang lain', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('impor.pratinjau'), ['files' => [
            UploadedFile::fake()->createWithContent('rusak.pdf', "%PDF-1.4\nrusak\n"),
            pdfPalsu('arkas-maret-2026', 'maret.pdf'),
        ]])
        ->assertSessionHas('pratinjau', fn (array $d) => $d[0]['ditolak'] === 'PDF ini rusak.'
            && ! isset($d[0]['token'])
            && $d[1]['ringkasan']['baru'] === 10);

    expect(Storage::disk('local')->files('impor-sementara'))->toHaveCount(1);
});

it('pratinjau menampilkan penolakan identitas tanpa menyimpan', function () {
    Sekolah::where('identitas', '20606714')->update(['identitas' => '1']);

    $this->actingAs(User::factory()->create())
        ->post(route('impor.pratinjau'), ['files' => [pdfPalsu('arkas-maret-2026')]])
        ->assertSessionHas('pratinjau', fn (array $d) => str_contains($d[0]['ditolak'], '20606714'));

    expect(Kegiatan::count())->toBe(0);
});

it('pratinjau memvalidasi berkas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('impor.pratinjau'), [])->assertSessionHasErrors('files');
    $this->actingAs($user)->post(route('impor.pratinjau'), ['files' => [UploadedFile::fake()->create('a.txt', 5, 'text/plain')]])
        ->assertSessionHasErrors('files.0');
    $this->actingAs($user)->post(route('impor.pratinjau'), ['files' => [UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf')]])
        ->assertSessionHasErrors('files.0');
});

it('pratinjau menghapus file sementara yang lebih tua dari sehari', function () {
    Storage::disk('local')->put('impor-sementara/lama.pdf', 'x');
    Storage::disk('local')->put('impor-sementara/baru.pdf', 'x');
    touch(Storage::disk('local')->path('impor-sementara/lama.pdf'), now()->subDays(2)->getTimestamp());

    $this->actingAs(User::factory()->create())
        ->post(route('impor.pratinjau'), ['files' => [pdfPalsu('arkas-maret-2026')]]);

    Storage::disk('local')->assertMissing('impor-sementara/lama.pdf');
    Storage::disk('local')->assertExists('impor-sementara/baru.pdf');
});

it('simpan menyimpan dan menghapus file sementara; simpan kedua tidak menggandakan dan tidak 500', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('impor.pratinjau'), ['files' => [pdfPalsu('arkas-maret-2026', 'maret.pdf')]]);
    $token = session('pratinjau')[0]['token'];

    $this->post(route('impor.simpan'), ['tokens' => [$token]])
        ->assertRedirect(route('impor.index'))
        ->assertSessionHas('hasil', fn (array $h) => $h[0]['nama_file'] === 'maret.pdf' && $h[0]['ringkasan']['baru'] === 10);

    expect(Kegiatan::count())->toBe(10)->and(ImporAnggaran::firstOrFail()->nama_file)->toBe('maret.pdf')
        ->and(Storage::disk('local')->files('impor-sementara'))->toHaveCount(0);

    $this->post(route('impor.simpan'), ['tokens' => [$token]])
        ->assertRedirect(route('impor.index'))
        ->assertSessionHas('hasil', fn (array $h) => $h[0]['ditolak'] === 'Pratinjau sudah tidak berlaku, unggah ulang');

    expect(Kegiatan::count())->toBe(10)->and(ImporAnggaran::count())->toBe(1);
});

it('simpan menolak token yang bukan UUID', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('impor.simpan'), ['tokens' => ['../../.env']])->assertSessionHasErrors('tokens.0');
    $this->actingAs($user)->post(route('impor.simpan'), [])->assertSessionHasErrors('tokens');
});

it('simpan dengan token yang tidak pernah ada memberi pesan kedaluwarsa', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('impor.simpan'), ['tokens' => [(string) Str::uuid()]])
        ->assertSessionHas('hasil', fn (array $h) => $h[0]['ditolak'] === 'Pratinjau sudah tidak berlaku, unggah ulang');
});

it('simpan per token berdiri sendiri: yang ditolak tidak menggagalkan yang lain', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('impor.pratinjau'), ['files' => [pdfPalsu('arkas-maret-2026', 'maret.pdf'), pdfPalsu('arkas-januari-2026', 'januari.pdf')]]);
    [$maret, $januari] = array_column(session('pratinjau'), 'token');

    // Sekolah dihapus identitasnya setelah pratinjau: kedua file ditolak saat simpan, tidak 500.
    Sekolah::where('identitas', '20606714')->update(['identitas' => '1']);
    $this->post(route('impor.simpan'), ['tokens' => [$maret, $januari]])
        ->assertSessionHas('hasil', fn (array $h) => count($h) === 2 && isset($h[0]['ditolak'], $h[1]['ditolak']));

    expect(Kegiatan::count())->toBe(0);
});
