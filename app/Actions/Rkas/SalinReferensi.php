<?php

declare(strict_types=1);

namespace App\Actions\Rkas;

use App\Models\RkasRefBarang;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalinReferensi
{
    /**
     * Salin kegiatan, rekening, dan barang acuan dari satu tahun ke tahun lain. Referensi yang
     * kedaluwarsa pada hari ini tidak ikut disalin, dan yang sudah ada di tahun tujuan dilewati.
     *
     * @return array{kegiatan: int, rekening: int, barang: int}
     */
    public function jalankan(int $dari, int $ke): array
    {
        return DB::transaction(function () use ($dari, $ke): array {
            $hasil = ['kegiatan' => 0, 'rekening' => 0, 'barang' => 0];

            $sudahKegiatan = RkasRefKegiatan::where('tahun', $ke)->get()
                ->mapWithKeys(fn (RkasRefKegiatan $k): array => [$k->sumber_dana.'|'.$k->kode => true]);
            foreach ($this->belumKedaluwarsa(RkasRefKegiatan::query(), $dari)->orderBy('id')->get() as $kegiatan) {
                if (isset($sudahKegiatan[$kegiatan->sumber_dana.'|'.$kegiatan->kode])) {
                    continue;
                }
                RkasRefKegiatan::create([
                    'tahun' => $ke,
                    'kode' => $kegiatan->kode,
                    'kode_induk' => $kegiatan->kode_induk,
                    'uraian' => $kegiatan->uraian,
                    'level' => $kegiatan->level,
                    'sumber_dana' => $kegiatan->sumber_dana,
                ]);
                $hasil['kegiatan']++;
            }

            $sudahRekening = RkasRefRekening::where('tahun', $ke)->pluck('kode')->flip();
            foreach ($this->belumKedaluwarsa(RkasRefRekening::query(), $dari)->orderBy('id')->get() as $rekening) {
                if (isset($sudahRekening[$rekening->kode])) {
                    continue;
                }
                RkasRefRekening::create(['tahun' => $ke, 'kode' => $rekening->kode, 'nama' => $rekening->nama]);
                $hasil['rekening']++;
            }

            $sudahBarang = RkasRefBarang::where('tahun', $ke)->get()
                ->mapWithKeys(fn (RkasRefBarang $b): array => [($b->kode_rekening ?? '').'|'.$b->nama => true]);
            foreach ($this->belumKedaluwarsa(RkasRefBarang::query(), $dari)->orderBy('id')->get() as $barang) {
                if (isset($sudahBarang[($barang->kode_rekening ?? '').'|'.$barang->nama])) {
                    continue;
                }
                RkasRefBarang::create([
                    'tahun' => $ke,
                    'kode_rekening' => $barang->kode_rekening,
                    'nama' => $barang->nama,
                    'satuan' => $barang->satuan,
                    'harga' => $barang->harga,
                    'batas_bawah' => $barang->batas_bawah,
                    'batas_atas' => $barang->batas_atas,
                ]);
                $hasil['barang']++;
            }

            return $hasil;
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function belumKedaluwarsa(Builder $query, int $tahun): Builder
    {
        return $query->where('tahun', $tahun)
            ->where(fn (Builder $q) => $q->whereNull('kedaluwarsa')->orWhereDate('kedaluwarsa', '>', now()->toDateString()));
    }
}
