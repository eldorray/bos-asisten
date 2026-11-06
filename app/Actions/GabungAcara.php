<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Acara;
use App\Models\Foto;
use App\Models\Kegiatan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GabungAcara
{
    /**
     * Gabungkan baris menjadi satu acara. Foto tiap baris pindah ke acara tanpa
     * menyentuh berkas di disk; kuitansi tetap per baris.
     *
     * @param  array<int, int>  $kegiatanIds
     */
    public function gabung(array $kegiatanIds, string $nama): Acara
    {
        return DB::transaction(function () use ($kegiatanIds, $nama): Acara {
            $kegiatans = Kegiatan::query()
                ->whereIn('id', $kegiatanIds)
                ->with('kuitansi')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->pastikanBoleh($kegiatans, count(array_unique($kegiatanIds)));

            $pertama = $kegiatans->firstOrFail();
            $acara = Acara::create([
                'sekolah_id' => $pertama->sekolah_id,
                'tahun' => $pertama->tahun,
                'bulan' => (int) $pertama->bulan,
                'nama' => $nama,
                'jenis_kegiatan_id' => $pertama->jenis_kegiatan_id,
            ]);

            $this->pindahkanFoto($kegiatans, $acara);
            Kegiatan::whereIn('id', $kegiatans->modelKeys())->update(['acara_id' => $acara->id]);

            return $acara;
        });
    }

    /**
     * Baris lain yang boleh digabung dengan baris ini.
     *
     * @return Collection<int, Kegiatan>
     */
    public function kandidat(Kegiatan $kegiatan): Collection
    {
        if (! $this->bebas($kegiatan) || $kegiatan->bulan === null) {
            return new Collection;
        }

        return Kegiatan::query()
            ->where('sekolah_id', $kegiatan->sekolah_id)
            ->where('tahun', $kegiatan->tahun)
            ->where('bulan', $kegiatan->bulan)
            ->where('jenis_kegiatan_id', $kegiatan->jenis_kegiatan_id)
            ->whereKeyNot($kegiatan->id)
            ->with('kuitansi')
            ->orderBy('id')
            ->get()
            ->filter(fn (Kegiatan $k): bool => $this->bebas($k))
            ->values();
    }

    /**
     * Bubarkan acara. Semua foto kembali ke baris pertama (menurut id), tidak ada yang hilang.
     */
    public function pisah(Acara $acara): void
    {
        DB::transaction(function () use ($acara): void {
            $pertama = $acara->kegiatans()->orderBy('id')->first();

            if ($pertama !== null) {
                $acara->fotos()->update(['pemilik_type' => $pertama->getMorphClass(), 'pemilik_id' => $pertama->id]);
            }

            $acara->kegiatans()->update(['acara_id' => null]);
            $acara->delete();
        });
    }

    /**
     * @param  Collection<int, Kegiatan>  $kegiatans
     */
    private function pastikanBoleh(Collection $kegiatans, int $diminta): void
    {
        $pesan = match (true) {
            $diminta < 2 || $kegiatans->count() < 2 => 'Pilih minimal dua baris untuk digabung.',
            $kegiatans->count() !== $diminta => 'Ada baris yang tidak ditemukan.',
            $kegiatans->contains(fn (Kegiatan $k): bool => $k->bulan === null) => 'Tentukan bulan kegiatan dulu sebelum digabung.',
            $kegiatans->pluck('sekolah_id')->unique()->count() > 1 => 'Baris harus dari sekolah yang sama.',
            $kegiatans->pluck('tahun')->unique()->count() > 1 || $kegiatans->pluck('bulan')->unique()->count() > 1 => 'Baris harus dari bulan dan tahun yang sama.',
            $kegiatans->pluck('jenis_kegiatan_id')->unique()->count() > 1 => 'Baris harus berjenis kegiatan yang sama.',
            $kegiatans->contains(fn (Kegiatan $k): bool => $k->acara_id !== null) => 'Ada baris yang sudah menjadi bagian acara lain.',
            $kegiatans->contains(fn (Kegiatan $k): bool => $k->status_manual !== null || $k->hilang_dari_anggaran) => 'Baris yang ditandai manual atau sudah tidak ada di anggaran tidak bisa digabung.',
            $kegiatans->contains(fn (Kegiatan $k): bool => $k->bulan_pelaksanaan !== null) => 'Baris yang ditunda tidak bisa digabung.',
            $kegiatans->contains(fn (Kegiatan $k): bool => $this->rapel($k)) => 'Baris bagian dari kuitansi rapel tidak bisa digabung.',
            default => null,
        };

        if ($pesan !== null) {
            throw ValidationException::withMessages(['kegiatan_ids' => $pesan]);
        }
    }

    private function bebas(Kegiatan $kegiatan): bool
    {
        return $kegiatan->acara_id === null
            && $kegiatan->status_manual === null
            && $kegiatan->bulan_pelaksanaan === null
            && ! $kegiatan->hilang_dari_anggaran
            && ! $this->rapel($kegiatan);
    }

    private function rapel(Kegiatan $kegiatan): bool
    {
        $kuitansi = $kegiatan->kuitansi->first();

        return $kuitansi !== null
            && ($kuitansi->bulan > $kegiatan->bulan || $kuitansi->kegiatans()->count() > 1);
    }

    /**
     * Foto baris pertama didahulukan; urutan dihitung ulang per jenis berkas.
     *
     * @param  Collection<int, Kegiatan>  $kegiatans
     */
    private function pindahkanFoto(Collection $kegiatans, Acara $acara): void
    {
        $nomor = [];

        foreach ($kegiatans as $kegiatan) {
            $kegiatan->fotos()->orderBy('urutan')->orderBy('id')->get()->each(function (Foto $foto) use ($acara, &$nomor): void {
                $nomor[$foto->jenis_berkas_id] = ($nomor[$foto->jenis_berkas_id] ?? 0) + 1;

                $foto->update([
                    'pemilik_type' => $acara->getMorphClass(),
                    'pemilik_id' => $acara->id,
                    'urutan' => $nomor[$foto->jenis_berkas_id],
                ]);
            });
        }
    }
}
