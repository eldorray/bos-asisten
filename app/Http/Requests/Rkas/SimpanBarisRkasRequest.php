<?php

declare(strict_types=1);

namespace App\Http\Requests\Rkas;

use App\Actions\Rkas\PesanRkas;
use App\Enums\SistemAnggaran;
use App\Models\RkasBaris;
use App\Models\RkasKertasKerja;
use App\Models\RkasPengaturan;
use App\Models\RkasRefBarang;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefRekening;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimpanBarisRkasRequest extends FormRequest
{
    /** Batas harga agar harga x 12 x 999.999 tidak melewati PHP_INT_MAX. */
    public const HARGA_MAKS = 700_000_000_000;

    public function baris(): ?RkasBaris
    {
        $model = $this->route('baris');

        return $model instanceof RkasBaris ? $model : null;
    }

    public function kertasKerja(): RkasKertasKerja
    {
        $model = $this->route('kertasKerja');
        if ($model instanceof RkasKertasKerja) {
            return $model;
        }

        $baris = $this->baris();
        abort_if($baris === null, 404);

        return $baris->kertasKerja;
    }

    protected function prepareForValidation(): void
    {
        abort_unless($this->kertasKerja()->sekolah->sistem === SistemAnggaran::Arkas, 404);

        $volume = $this->input('volume_bulan');
        if (is_array($volume)) {
            $this->merge(['volume_bulan' => array_map(fn ($v) => $v === null || $v === '' ? 0 : $v, $volume)]);
        }
    }

    private function kegiatanHonor(): bool
    {
        $kegiatan = RkasRefKegiatan::query()->find($this->integer('ref_kegiatan_id'));
        if ($kegiatan === null) {
            return false;
        }

        $kertasKerja = $this->kertasKerja();

        return in_array($kegiatan->kode, RkasPengaturan::untuk($kertasKerja->sekolah, $kertasKerja->tahun)->kode_honor, true);
    }

    /**
     * Barang wajib dipilih (dan menjadi sumber uraian/satuan) hanya bila rekening punya daftar barang.
     */
    private function barangWajib(): bool
    {
        $rekening = RkasRefRekening::query()->find($this->integer('ref_rekening_id'));

        return $rekening !== null
            && RkasRefBarang::query()->untukRekening($rekening)->belumKedaluwarsa()->exists();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $honor = $this->kegiatanHonor();
        $barangWajib = ! $honor && $this->barangWajib();
        $barangAda = ! $honor && $this->filled('ref_barang_id');
        $bebas = ! $honor && ! $barangWajib && ! $barangAda;

        return [
            'ref_kegiatan_id' => ['required', 'integer'],
            'ref_rekening_id' => ['required', 'integer'],
            'ref_barang_id' => [Rule::requiredIf($barangWajib), 'nullable', 'integer'],
            'ptk_id' => [Rule::requiredIf($honor), 'nullable', 'integer'],
            'uraian' => [Rule::requiredIf($bebas), 'nullable', 'string', 'max:500'],
            'satuan' => [Rule::requiredIf($honor || $bebas), 'nullable', 'string', Rule::exists('rkas_ref_satuans', 'nama')],
            'harga' => ['required', 'integer', 'min:1', 'max:'.self::HARGA_MAKS],
            'volume_bulan' => ['required', 'array'],
            'volume_bulan.*' => ['integer', 'min:0', 'max:999999'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => PesanRkas::WAJIB,
            'harga.integer' => PesanRkas::WAJIB,
            'harga.min' => PesanRkas::WAJIB,
            'harga.max' => PesanRkas::HARGA_MAKS,
            'uraian.max' => PesanRkas::URAIAN_MAKS,
            'volume_bulan.*.integer' => PesanRkas::VOLUME_MAKS,
            'volume_bulan.*.min' => PesanRkas::VOLUME_MAKS,
            'volume_bulan.*.max' => PesanRkas::VOLUME_MAKS,
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $kertasKerja = $this->kertasKerja();
            $baris = $this->baris();

            $kegiatanLama = $baris?->ref_kegiatan_id === $this->integer('ref_kegiatan_id');
            $tersedia = $kegiatanLama || RkasRefKegiatan::query()
                ->tersediaUntuk($kertasKerja->tahun, $kertasKerja->sumber_dana)
                ->whereKey($this->integer('ref_kegiatan_id'))
                ->exists();
            if (! $tersedia) {
                $validator->errors()->add('ref_kegiatan_id', PesanRkas::KEGIATAN_BERUBAH);
            }

            $rekeningLama = $baris?->ref_rekening_id === $this->integer('ref_rekening_id');
            $rekeningAda = RkasRefRekening::query()
                ->where('tahun', $kertasKerja->tahun)
                ->when(! $rekeningLama, fn ($q) => $q->belumKedaluwarsa())
                ->whereKey($this->integer('ref_rekening_id'))
                ->exists();
            if (! $rekeningAda) {
                $validator->errors()->add('ref_rekening_id', PesanRkas::REKENING_BERUBAH);
            }

            if ($this->filled('ref_barang_id')) {
                $barangLama = $baris?->ref_barang_id === $this->integer('ref_barang_id');
                $barangAda = RkasRefBarang::query()
                    ->where('tahun', $kertasKerja->tahun)
                    ->when(! $barangLama, fn ($q) => $q->belumKedaluwarsa())
                    ->whereKey($this->integer('ref_barang_id'))
                    ->exists();
                if (! $barangAda) {
                    $validator->errors()->add('ref_barang_id', PesanRkas::BARANG_BERUBAH);
                }
            }

            foreach (array_keys((array) $this->input('volume_bulan')) as $bulan) {
                if (! is_numeric($bulan) || (int) $bulan < 1 || (int) $bulan > 12) {
                    $validator->errors()->add('volume_bulan', PesanRkas::VOLUME_MAKS);
                }
            }
        }];
    }

    /**
     * @return array{ref_kegiatan_id: int, ref_rekening_id: int, ref_barang_id: int|null, ptk_id: int|null, uraian: string|null, satuan: string|null, harga: int, volume_bulan: array<int|string, int|string|null>, keterangan: string|null}
     */
    public function dataBaris(): array
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();

        return [
            'ref_kegiatan_id' => (int) $v['ref_kegiatan_id'],
            'ref_rekening_id' => (int) $v['ref_rekening_id'],
            'ref_barang_id' => isset($v['ref_barang_id']) ? (int) $v['ref_barang_id'] : null,
            'ptk_id' => isset($v['ptk_id']) ? (int) $v['ptk_id'] : null,
            'uraian' => isset($v['uraian']) ? (string) $v['uraian'] : null,
            'satuan' => isset($v['satuan']) ? (string) $v['satuan'] : null,
            'harga' => (int) $v['harga'],
            'volume_bulan' => $v['volume_bulan'],
            'keterangan' => isset($v['keterangan']) ? (string) $v['keterangan'] : null,
        ];
    }
}
