<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rkas;

use App\Actions\Rkas\HapusReferensi;
use App\Actions\Rkas\MuatKegiatanResmi;
use App\Actions\Rkas\MuatRekeningResmi;
use App\Actions\Rkas\SalinReferensi;
use App\Enums\JenisPtk;
use App\Enums\SistemAnggaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rkas\SalinReferensiRkasRequest;
use App\Http\Requests\Rkas\SimpanReferensiRkasRequest;
use App\Models\RkasPtk;
use App\Models\RkasRefBarang;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use App\Models\RkasRefSatuan;
use App\Models\Sekolah;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferensiRkasController extends Controller
{
    private const PER_HALAMAN = 50;

    private const MODEL = [
        'kegiatan' => RkasRefKegiatan::class,
        'rekening' => RkasRefRekening::class,
        'barang' => RkasRefBarang::class,
        'satuan' => RkasRefSatuan::class,
        'ptk' => RkasPtk::class,
    ];

    public function index(Request $request): Response
    {
        $jenis = $request->string('jenis', 'kegiatan')->toString();
        abort_unless(isset(self::MODEL[$jenis]), 404);

        $tahun = $request->integer('tahun') ?: now()->year + 1;
        abort_unless($tahun >= 2000 && $tahun <= 2100, 404);

        $sekolahs = Sekolah::query()->where('sistem', SistemAnggaran::Arkas)->orderBy('nama')->get();
        $sekolah = $request->filled('sekolah')
            ? $sekolahs->firstWhere('uuid', $request->string('sekolah')->toString())
            : $sekolahs->first();
        abort_if($sekolah === null, 404);

        $dapatDisalin = in_array($jenis, ['kegiatan', 'rekening', 'barang'], true)
            && ! self::MODEL[$jenis]::query()->where('tahun', $tahun)->exists()
            && self::MODEL[$jenis]::query()->where('tahun', $tahun - 1)->exists();

        return Inertia::render('pengaturan/ReferensiRkas', [
            'jenis' => $jenis,
            'tahun' => $tahun,
            'sekolah' => ['id' => $sekolah->id, 'uuid' => $sekolah->uuid, 'nama' => $sekolah->nama],
            'pilihanSekolah' => $sekolahs->map(fn (Sekolah $s): array => ['id' => $s->id, 'uuid' => $s->uuid, 'nama' => $s->nama])->values(),
            'daftar' => $this->daftar($jenis, $tahun, $sekolah),
            'dapatDisalin' => $dapatDisalin,
            'pilihanJenisPtk' => array_map(
                fn (JenisPtk $j): array => ['nilai' => $j->value, 'nama' => $j->name],
                JenisPtk::cases(),
            ),
        ]);
    }

    public function store(SimpanReferensiRkasRequest $request, string $jenis): RedirectResponse
    {
        $modelClass = self::MODEL[$jenis];
        $modelClass::create($request->dataReferensi());

        return back();
    }

    public function destroy(string $jenis, string $uuid, HapusReferensi $hapus): RedirectResponse
    {
        abort_unless(isset(self::MODEL[$jenis]), 404);

        $ref = self::MODEL[$jenis]::query()->where('uuid', $uuid)->firstOrFail();

        if ($ref instanceof RkasPtk) {
            abort_unless($ref->sekolah->sistem === SistemAnggaran::Arkas, 404);
        }

        $alasan = $hapus->jalankan($ref);

        return $alasan === null ? back() : back()->withErrors(['hapus' => $alasan]);
    }

    public function salin(SalinReferensiRkasRequest $request, SalinReferensi $salin): RedirectResponse
    {
        $salin->jalankan($request->integer('dari'), $request->integer('ke'));

        return back();
    }

    public function muatKegiatan(Request $request, MuatKegiatanResmi $muat): RedirectResponse
    {
        $tahun = (int) $request->validate(['tahun' => ['required', 'integer', 'between:2000,2100']])['tahun'];
        $baru = $muat->jalankan($tahun);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $baru === 0 ? "Kode kegiatan {$tahun} sudah lengkap" : "{$baru} kode kegiatan {$tahun} dimuat",
        ]);

        return back();
    }

    public function muatRekening(Request $request, MuatRekeningResmi $muat): RedirectResponse
    {
        $tahun = (int) $request->validate(['tahun' => ['required', 'integer', 'between:2000,2100']])['tahun'];
        $baru = $muat->jalankan($tahun);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $baru['rekening'] + $baru['satuan'] === 0
                ? "Rekening {$tahun} dan satuan sudah lengkap"
                : "{$baru['rekening']} rekening {$tahun} dan {$baru['satuan']} satuan dimuat",
        ]);

        return back();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function daftar(string $jenis, int $tahun, Sekolah $sekolah): LengthAwarePaginator
    {
        /** @var Builder<Model> $query */
        $query = self::MODEL[$jenis]::query();

        $query = match ($jenis) {
            'kegiatan' => $query->where('tahun', $tahun)->orderBy('kode'),
            'rekening' => $query->where('tahun', $tahun)->orderBy('kode'),
            'barang' => $query->where('tahun', $tahun)->orderBy('nama')->orderBy('id'),
            'satuan' => $query->orderBy('nama'),
            default => $query->where('sekolah_id', $sekolah->id)->orderBy('nama'),
        };

        return $query->paginate(self::PER_HALAMAN)->withQueryString()
            ->through(fn (Model $m): array => $this->baris($jenis, $m));
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(string $jenis, Model $m): array
    {
        return match (true) {
            $m instanceof RkasRefKegiatan => [
                'id' => $m->id, 'uuid' => $m->uuid, 'kode' => $m->kode, 'uraian' => $m->uraian, 'level' => $m->level,
                'sumber_dana' => $m->sumber_dana, 'kedaluwarsa' => $m->kedaluwarsa?->toDateString(),
            ],
            $m instanceof RkasRefRekening => [
                'id' => $m->id, 'uuid' => $m->uuid, 'kode' => $m->kode, 'nama' => $m->nama, 'kedaluwarsa' => $m->kedaluwarsa?->toDateString(),
            ],
            $m instanceof RkasRefBarang => [
                'id' => $m->id, 'uuid' => $m->uuid, 'kode_rekening' => $m->kode_rekening, 'nama' => $m->nama, 'satuan' => $m->satuan,
                'harga' => $m->harga, 'batas_bawah' => $m->batas_bawah, 'batas_atas' => $m->batas_atas,
                'kedaluwarsa' => $m->kedaluwarsa?->toDateString(),
            ],
            $m instanceof RkasPtk => [
                'id' => $m->id, 'uuid' => $m->uuid, 'nama' => $m->nama, 'nuptk' => $m->nuptk, 'jenis' => $m->jenis->value,
            ],
            default => ['id' => $m->getKey(), 'uuid' => $m->getAttribute('uuid'), 'nama' => $m->getAttribute('nama')],
        };
    }
}
