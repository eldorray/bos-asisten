<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Foto;
use App\Models\Kegiatan;
use App\Models\Kuitansi;
use App\Models\Sekolah;
use App\Pdf\BlokPdf;
use App\Pdf\LampiranPdf;
use Illuminate\Support\Collection;

class SusunBlokBulan
{
    /**
     * Isi PDF satu sekolah satu bulan, urut nomor bukti; yang belum berkuitansi di akhir.
     *
     * @return list<BlokPdf>
     */
    public static function untuk(Sekolah $sekolah, int $tahun, int $bulan): array
    {
        return array_values(self::kegiatan($sekolah, $tahun, $bulan)
            ->filter(fn (Kegiatan $k): bool => $k->kuitansi->isNotEmpty() || $k->pemilikBerkas()->fotos->isNotEmpty())
            ->groupBy(self::kunciBlok(...))
            ->sortBy(fn (Collection $baris): array => [
                $baris->flatMap(fn (Kegiatan $k): Collection => $k->kuitansi)->min('nomor_urut') ?? PHP_INT_MAX,
                $baris->min('id'),
            ])
            ->map(self::blok(...))
            ->all());
    }

    /**
     * Isi PDF satu kegiatan: kuitansi, lalu foto menurut urutan jenis berkas (nota/struk dulu, foto barang terakhir).
     * Relasi HitungStatusKegiatan::RELASI plus fotos.jenisBerkas dan acara.fotos.jenisBerkas harus sudah dimuat.
     */
    public static function untukKegiatan(Kegiatan $kegiatan): BlokPdf
    {
        return self::blok(new Collection([$kegiatan]));
    }

    /**
     * Baris berkuitansi ikut bulan kuitansinya, sehingga rapel masuk bulan terakhir cakupannya
     * dan tidak muncul di bulan lain. Baris tanpa kuitansi ikut bulannya sendiri.
     *
     * @return Collection<int, Kegiatan>
     */
    private static function kegiatan(Sekolah $sekolah, int $tahun, int $bulan): Collection
    {
        return Kegiatan::query()
            ->where('sekolah_id', $sekolah->id)
            ->where('tahun', $tahun)
            ->whereNull('status_manual')
            ->where(fn ($q) => $q
                ->whereDoesntHave('kuitansi')->padaBulan($bulan)
                ->orWhereHas('kuitansi', fn ($k) => $k->where('tahun', $tahun)->where('bulan', $bulan)))
            ->with(['jenisKegiatan.jenisBerkas', 'fotos.jenisBerkas', 'acara.fotos.jenisBerkas', 'kuitansi.kegiatans'])
            ->orderBy('bulan')
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    private static function kunciBlok(Kegiatan $kegiatan): string
    {
        return match (true) {
            $kegiatan->acara_id !== null => "acara-{$kegiatan->acara_id}",
            $kegiatan->kuitansi->isNotEmpty() => 'kuitansi-'.$kegiatan->kuitansi->firstOrFail()->id,
            default => "kegiatan-{$kegiatan->id}",
        };
    }

    /**
     * @param  Collection<int, Kegiatan>  $baris
     */
    private static function blok(Collection $baris): BlokPdf
    {
        return new BlokPdf(self::daftarKuitansi($baris), self::lampiran($baris));
    }

    /**
     * Baris tanpa kuitansi tergambar sebagai kotak "belum dibuat" setelah kuitansi yang sudah ada.
     *
     * @param  Collection<int, Kegiatan>  $baris
     * @return list<?Kuitansi>
     */
    private static function daftarKuitansi(Collection $baris): array
    {
        $ada = $baris
            ->flatMap(fn (Kegiatan $k): Collection => $k->kuitansi)
            ->unique('id')
            ->sortBy('nomor_urut')
            ->values()
            ->all();
        $belum = $baris->filter(fn (Kegiatan $k): bool => $k->kuitansi->isEmpty())->count();

        return [...$ada, ...array_fill(0, $belum, null)];
    }

    /**
     * Foto bersama satu acara hanya diambil sekali. Urut bulan lalu urutan jenis berkas;
     * foto dari jenis yang tidak ada lagi di daftar paling akhir.
     *
     * @param  Collection<int, Kegiatan>  $baris
     * @return list<LampiranPdf>
     */
    private static function lampiran(Collection $baris): array
    {
        $daftar = [];
        $terambil = [];

        foreach ($baris as $kegiatan) {
            $pemilik = $kegiatan->pemilikBerkas();
            $kunci = $pemilik::class.'-'.$pemilik->id;

            if (isset($terambil[$kunci])) {
                continue;
            }

            $terambil[$kunci] = true;
            $urutanJenis = $kegiatan->jenisKegiatan->jenisBerkas->pluck('id')->flip();

            foreach ($pemilik->fotos as $foto) {
                $posisi = $urutanJenis->get($foto->jenis_berkas_id);

                $daftar[] = [
                    'urut' => [$posisi === null ? 1 : 0, $kegiatan->bulan, $posisi, $foto->urutan, $foto->id],
                    'foto' => $foto,
                ];
            }
        }

        usort($daftar, fn (array $a, array $b): int => $a['urut'] <=> $b['urut']);

        return array_map(fn (array $d): LampiranPdf => self::lampiranDari($d['foto']), $daftar);
    }

    private static function lampiranDari(Foto $foto): LampiranPdf
    {
        return new LampiranPdf($foto->path, $foto->lebar, $foto->tinggi, $foto->jenisBerkas->tata_letak);
    }
}
