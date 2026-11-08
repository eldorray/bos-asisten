<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;
use App\Models\RkasPtk;
use App\Models\RkasRefBarang;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use App\Models\RkasRefSatuan;
use Illuminate\Support\Collection;

/**
 * Menyusun props Inertia untuk baris belanja dan pilihan referensinya (hanya kolom yang dipakai halaman).
 */
class PropsBarisRkas
{
    /**
     * @param  array<int, list<string>>  $masalah  pesan per id baris (dari RingkasanRkas); BULAN_LEWAT dipisah sebagai peringatan tidak memblokir
     * @return array<int, array<string, mixed>>
     */
    public function baris(RkasKertasKerja $kertasKerja, array $masalah = []): array
    {
        $daftar = $kertasKerja->baris()
            ->with(['refKegiatan', 'refRekening', 'refBarang'])
            ->get()
            ->sortBy([
                fn (RkasBaris $a, RkasBaris $b): int => strcmp($a->refKegiatan->kode, $b->refKegiatan->kode),
                fn (RkasBaris $a, RkasBaris $b): int => strcmp($a->refRekening->kode, $b->refRekening->kode),
                ['urutan', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $kodeHonor = RkasPengaturan::untuk($kertasKerja->sekolah, $kertasKerja->tahun)->kode_honor;
        $barangAktif = RkasRefBarang::query()->where('tahun', $kertasKerja->tahun)->belumKedaluwarsa()->get(['kode_rekening']);
        $kodeBerdaftar = $barangAktif->pluck('kode_rekening')->filter()->unique()->flip();
        $adaTanpaKode = $barangAktif->contains(fn (RkasRefBarang $b): bool => $b->kode_rekening === null);

        $induk = $this->induk($kertasKerja->tahun, $daftar->map(fn (RkasBaris $b): RkasRefKegiatan => $b->refKegiatan));

        return $daftar->map(fn (RkasBaris $b): array => [
            'id' => $b->id,
            'urutan' => $b->urutan,
            'ref_kegiatan_id' => $b->ref_kegiatan_id,
            'ref_rekening_id' => $b->ref_rekening_id,
            'ref_barang_id' => $b->ref_barang_id,
            'ptk_id' => $b->ptk_id,
            'kegiatan' => $this->kegiatan($b->refKegiatan, $induk),
            'rekening' => ['kode' => $b->refRekening->kode, 'nama' => $b->refRekening->nama],
            // Referensi milik baris sendiri, agar form ubah tetap utuh walau referensinya sudah kedaluwarsa.
            'kegiatan_honor' => in_array($b->refKegiatan->kode, $kodeHonor, true),
            'rekening_punya_barang' => $kodeBerdaftar->has($b->refRekening->kode)
                || ($adaTanpaKode && str_starts_with($b->refRekening->kode, '5.2')),
            'barang' => $b->refBarang === null ? null : [
                'id' => $b->refBarang->id,
                'nama' => $b->refBarang->nama,
                'satuan' => $b->refBarang->satuan,
                'harga' => $b->refBarang->harga,
                'batas_bawah' => $b->refBarang->batas_bawah,
                'batas_atas' => $b->refBarang->batas_atas,
                'kode_rekening' => $b->refBarang->kode_rekening,
            ],
            'uraian' => $b->uraian,
            'satuan' => $b->satuan,
            'harga' => $b->harga,
            'volume_bulan' => $b->volume_bulan,
            'volume' => $b->volume(),
            'jumlah' => $b->jumlah(),
            'keterangan' => $b->keterangan,
            'masalah' => array_values(array_filter($masalah[$b->id] ?? [], fn (string $pesan): bool => $pesan !== PesanRkas::BULAN_LEWAT)),
            'peringatan' => in_array(PesanRkas::BULAN_LEWAT, $masalah[$b->id] ?? [], true) ? [PesanRkas::BULAN_LEWAT] : [],
        ])->values()->all();
    }

    /**
     * @return array{kegiatan: array<int, array<string, mixed>>, rekening: array<int, array<string, mixed>>, barang: array<int, array<string, mixed>>, satuan: array<int, array<string, mixed>>, ptk: array<int, array<string, mixed>>}
     */
    public function pilihan(RkasKertasKerja $kertasKerja): array
    {
        $tahun = $kertasKerja->tahun;
        $kodeHonor = RkasPengaturan::untuk($kertasKerja->sekolah, $tahun)->kode_honor;

        $kegiatan = RkasRefKegiatan::query()->tersediaUntuk($tahun, $kertasKerja->sumber_dana)->orderBy('kode')->get();
        $induk = $this->induk($tahun, $kegiatan);

        $barang = RkasRefBarang::query()->where('tahun', $tahun)->belumKedaluwarsa()->orderBy('nama')->get();
        $kodeBerdaftar = $barang->pluck('kode_rekening')->filter()->unique()->flip();
        $adaTanpaKode = $barang->contains(fn (RkasRefBarang $b): bool => $b->kode_rekening === null);

        return [
            'kegiatan' => $kegiatan->map(fn (RkasRefKegiatan $k): array => $this->kegiatan($k, $induk) + [
                'id' => $k->id,
                'honor' => in_array($k->kode, $kodeHonor, true),
            ])->values()->all(),
            'rekening' => RkasRefRekening::query()->where('tahun', $tahun)->belumKedaluwarsa()->orderBy('kode')->get()
                ->map(fn (RkasRefRekening $r): array => [
                    'id' => $r->id,
                    'kode' => $r->kode,
                    'nama' => $r->nama,
                    // Barang wajib dipilih hanya bila rekening punya daftar barang (lihat SimpanBarisRkas).
                    'punya_barang' => $kodeBerdaftar->has($r->kode) || ($adaTanpaKode && str_starts_with($r->kode, '5.2')),
                ])->values()->all(),
            'barang' => $barang->map(fn (RkasRefBarang $b): array => [
                'id' => $b->id,
                'nama' => $b->nama,
                'satuan' => $b->satuan,
                'harga' => $b->harga,
                'batas_bawah' => $b->batas_bawah,
                'batas_atas' => $b->batas_atas,
                'kode_rekening' => $b->kode_rekening,
            ])->values()->all(),
            'satuan' => RkasRefSatuan::query()->orderBy('nama')->get()
                ->map(fn (RkasRefSatuan $s): array => ['id' => $s->id, 'nama' => $s->nama])->values()->all(),
            'ptk' => RkasPtk::query()->where('sekolah_id', $kertasKerja->sekolah_id)->orderBy('nama')->get()
                ->map(fn (RkasPtk $p): array => ['id' => $p->id, 'nama' => $p->nama, 'nuptk' => $p->nuptk, 'jenis' => $p->jenis->value])
                ->values()->all(),
        ];
    }

    /**
     * Komponen (level 2) dan program (level 1) dari kegiatan yang diberikan, dikunci per kode.
     *
     * @param  Collection<int, RkasRefKegiatan>  $kegiatan
     * @return Collection<string, RkasRefKegiatan>
     */
    private function induk(int $tahun, Collection $kegiatan): Collection
    {
        $kode = $kegiatan->pluck('kode_induk')->filter()->unique();
        $program = $kode->map(fn (string $k): string => explode('.', $k)[0].'.');

        return RkasRefKegiatan::query()
            ->where('tahun', $tahun)
            ->whereIn('kode', $kode->merge($program)->unique()->values())
            ->whereIn('level', [1, 2])
            ->orderBy('id')
            ->get()
            ->unique('kode')
            ->keyBy('kode');
    }

    /**
     * @param  Collection<string, RkasRefKegiatan>  $induk
     * @return array{kode: string, uraian: string, induk: array{kode: string, uraian: string}|null, program: array{kode: string, uraian: string}|null}
     */
    private function kegiatan(RkasRefKegiatan $k, Collection $induk): array
    {
        $komponen = $k->kode_induk !== null ? $induk->get($k->kode_induk) : null;
        $program = $k->kode_induk !== null ? $induk->get(explode('.', $k->kode_induk)[0].'.') : null;

        return [
            'kode' => $k->kode,
            'uraian' => $k->uraian,
            'induk' => $komponen !== null ? ['kode' => $komponen->kode, 'uraian' => $komponen->uraian] : null,
            'program' => $program !== null ? ['kode' => $program->kode, 'uraian' => $program->uraian] : null,
        ];
    }
}
