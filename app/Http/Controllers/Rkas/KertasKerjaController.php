<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rkas;

use App\Actions\Rkas\HitungRingkasan;
use App\Actions\Rkas\PropsBarisRkas;
use App\Actions\Rkas\SimpanKertasKerja;
use App\Enums\SistemAnggaran;
use App\Enums\StatusKertasKerja;
use App\Enums\SumberDanaRkas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rkas\SimpanKertasKerjaRequest;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPemberiDana;
use App\Models\Sekolah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KertasKerjaController extends Controller
{
    public function index(Request $request): Response
    {
        $tahun = $request->integer('tahun') ?: now()->year + 1;
        abort_unless($tahun >= 2000 && $tahun <= 2100, 404);

        $sekolahs = Sekolah::query()->where('sistem', SistemAnggaran::Arkas)->orderBy('nama')->get();
        $sekolah = $request->filled('sekolah_id')
            ? $sekolahs->firstWhere('id', $request->integer('sekolah_id'))
            : $sekolahs->first();
        abort_if($sekolah === null, 404);

        $daftar = RkasKertasKerja::query()
            ->where('sekolah_id', $sekolah->id)
            ->where('tahun', $tahun)
            ->with('baris')
            ->orderBy('sumber_dana')
            ->orderBy('nomor_perubahan')
            ->orderBy('nomor_pergeseran')
            ->orderBy('id')
            ->get()
            ->map(function (RkasKertasKerja $k): array {
                $dianggarkan = $k->baris->sum(fn (RkasBaris $b): int => $b->jumlah());

                return [
                    'id' => $k->id,
                    'label' => $k->label(),
                    'sumber_dana' => $k->sumber_dana->value,
                    'versi' => $k->labelVersi(),
                    'status' => $k->status->value,
                    'status_label' => $k->status->label(),
                    'pagu' => $k->pagu,
                    'dianggarkan' => $dianggarkan,
                    'sisa' => $k->pagu - $dianggarkan,
                    'bisa_diubah' => $k->bisaDiubah(),
                ];
            })
            ->values();

        return Inertia::render('rkas/Index', [
            'tahun' => $tahun,
            'sekolah' => ['id' => $sekolah->id, 'nama' => $sekolah->nama],
            'pilihanSekolah' => $sekolahs->map(fn (Sekolah $s): array => ['id' => $s->id, 'nama' => $s->nama])->values(),
            'pilihanSumberDana' => array_map(
                fn (SumberDanaRkas $s): array => ['nilai' => $s->value, 'nama' => $s === SumberDanaRkas::BospReguler ? 'BOSP Reguler' : 'Lainnya'],
                SumberDanaRkas::cases(),
            ),
            'daftar' => $daftar,
        ]);
    }

    public function store(SimpanKertasKerjaRequest $request, SimpanKertasKerja $simpan): RedirectResponse
    {
        /** @var array{tahun: int, sumber_dana: string, pagu?: int, pemberi_dana?: list<array{nama: string, jumlah: int}>} $data */
        $data = $request->safe()->except('sekolah_id');

        $kertasKerja = $simpan->buat($request->sekolah(), $data);

        return to_route('rkas.show', $kertasKerja);
    }

    public function show(RkasKertasKerja $kertasKerja, PropsBarisRkas $props, HitungRingkasan $hitung): Response
    {
        $sekolah = $kertasKerja->sekolah;
        abort_unless($sekolah->sistem === SistemAnggaran::Arkas, 404);

        $ringkasan = $hitung->untuk($kertasKerja);
        $rantai = $kertasKerja->rantai();

        return Inertia::render('rkas/Show', [
            'kertasKerja' => [
                'id' => $kertasKerja->id,
                'label' => $kertasKerja->label(),
                'tahun' => $kertasKerja->tahun,
                'sumber_dana' => $kertasKerja->sumber_dana->value,
                'pagu' => $kertasKerja->pagu,
                'status' => $kertasKerja->status->value,
                'status_label' => $kertasKerja->status->label(),
                'jenis_versi' => $kertasKerja->jenis_versi->value,
                'versi' => $kertasKerja->labelVersi(),
                'nomor_pergeseran' => $kertasKerja->nomor_pergeseran,
                'nomor_perubahan' => $kertasKerja->nomor_perubahan,
                'tanggal_revisi' => $kertasKerja->tanggal_revisi?->toDateString(),
                'bisa_diubah' => $kertasKerja->bisaDiubah(),
                'bisa_direvisi' => $kertasKerja->status === StatusKertasKerja::Disahkan && ! $rantai->contains('induk_id', $kertasKerja->id),
                'sekolah' => ['id' => $sekolah->id, 'nama' => $sekolah->nama, 'identitas' => $sekolah->identitas],
            ],
            'rantai' => $rantai->map(fn (RkasKertasKerja $k): array => [
                'id' => $k->id,
                'versi' => $k->labelVersi(),
                'status_label' => $k->status->label(),
                'tanggal_revisi' => $k->tanggal_revisi?->toDateString(),
            ])->values(),
            'pemberiDana' => $kertasKerja->pemberiDanas()->orderBy('id')->get()
                ->map(fn (RkasPemberiDana $p): array => ['id' => $p->id, 'nama' => $p->nama, 'jumlah' => $p->jumlah])
                ->values(),
            'baris' => $props->baris($kertasKerja, $ringkasan->masalahPerBaris),
            'ringkasan' => $ringkasan->props(),
            'pilihan' => $props->pilihan($kertasKerja),
        ]);
    }

    public function update(SimpanKertasKerjaRequest $request, RkasKertasKerja $kertasKerja, SimpanKertasKerja $simpan): RedirectResponse
    {
        abort_unless($kertasKerja->sekolah->sistem === SistemAnggaran::Arkas, 404);

        /** @var array{pagu?: int, pemberi_dana?: list<array{nama: string, jumlah: int}>} $data */
        $data = $request->validated();

        $simpan->ubahPagu($kertasKerja, $data);

        return back();
    }

    public function destroy(RkasKertasKerja $kertasKerja, SimpanKertasKerja $simpan): RedirectResponse
    {
        abort_unless($kertasKerja->sekolah->sistem === SistemAnggaran::Arkas, 404);

        $alasan = $simpan->hapus($kertasKerja);

        return $alasan === null
            ? to_route('rkas.index', ['tahun' => $kertasKerja->tahun, 'sekolah_id' => $kertasKerja->sekolah_id])
            : back()->withErrors(['hapus' => $alasan]);
    }
}
