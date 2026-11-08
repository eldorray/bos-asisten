<?php

declare(strict_types=1);

namespace App\Http\Requests\Rkas;

use App\Enums\JenisPtk;
use App\Enums\SistemAnggaran;
use App\Enums\SumberDanaRkas;
use App\Models\RkasRefKegiatan;
use App\Models\RkasRefSatuan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimpanReferensiRkasRequest extends FormRequest
{
    public function jenis(): string
    {
        return (string) $this->route('jenis');
    }

    protected function prepareForValidation(): void
    {
        foreach (['nuptk', 'kode_rekening'] as $kunci) {
            if ($this->input($kunci) === '') {
                $this->merge([$kunci => null]);
            }
        }

        foreach (['harga', 'batas_bawah', 'batas_atas'] as $kunci) {
            if ($this->has($kunci) && $this->input($kunci) === '') {
                $this->merge([$kunci => 0]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tahun = ['required', 'integer', 'between:2000,2100'];

        return match ($this->jenis()) {
            'kegiatan' => [
                'tahun' => $tahun,
                'kode' => ['required', 'string', 'max:30', 'regex:/^\d{2}(\.\d{2})*\.$/', Rule::unique('rkas_ref_kegiatans', 'kode')
                    ->where('tahun', $this->input('tahun'))
                    ->where('sumber_dana', $this->input('sumber_dana', SumberDanaRkas::BospReguler->value))],
                'uraian' => ['required', 'string', 'max:500'],
                'sumber_dana' => ['nullable', Rule::enum(SumberDanaRkas::class)],
            ],
            'rekening' => [
                'tahun' => $tahun,
                'kode' => ['required', 'string', 'regex:/^\d(\.\d+){5}$/', Rule::unique('rkas_ref_rekenings', 'kode')->where('tahun', $this->input('tahun'))],
                'nama' => ['required', 'string', 'max:255'],
            ],
            'barang' => [
                'tahun' => $tahun,
                'kode_rekening' => ['nullable', 'string', 'regex:/^\d(\.\d+){5}$/'],
                'nama' => ['required', 'string', 'max:255'],
                'satuan' => ['required', 'string', 'max:50'],
                'harga' => ['required', 'integer', 'min:0'],
                'batas_bawah' => ['required', 'integer', 'min:0'],
                'batas_atas' => ['required', 'integer', 'min:0'],
            ],
            'satuan' => [
                'nama' => ['required', 'string', 'max:50'],
            ],
            default => [
                'sekolah_id' => ['required', 'integer', Rule::exists('sekolahs', 'id')->where('sistem', SistemAnggaran::Arkas->value)],
                'nama' => ['required', 'string', 'max:255'],
                'nuptk' => ['nullable', 'string', 'regex:/^\d{16}$/'],
                'jenis' => ['required', Rule::enum(JenisPtk::class)],
            ],
        };
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            match ($this->jenis()) {
                'kegiatan' => $this->periksaKegiatan($validator),
                'barang' => $this->periksaBarang($validator),
                'satuan' => $this->periksaSatuan($validator),
                default => null,
            };
        }];
    }

    /**
     * Data siap simpan, termasuk level dan induk kegiatan yang diturunkan dari kode.
     *
     * @return array<string, mixed>
     */
    public function dataReferensi(): array
    {
        $data = $this->validated();

        if ($this->jenis() === 'kegiatan') {
            $data['level'] = $this->levelKegiatan();
            $data['kode_induk'] = $this->kodeInduk();
            $data['sumber_dana'] ??= SumberDanaRkas::BospReguler->value;
        }

        if ($this->jenis() === 'satuan') {
            $data['nama'] = trim((string) $data['nama']);
        }

        return $data;
    }

    private function levelKegiatan(): int
    {
        return substr_count((string) $this->input('kode'), '.');
    }

    private function kodeInduk(): ?string
    {
        $segmen = explode('.', rtrim((string) $this->input('kode'), '.'));

        return count($segmen) > 1 ? implode('.', array_slice($segmen, 0, -1)).'.' : null;
    }

    private function periksaKegiatan(Validator $validator): void
    {
        $level = $this->levelKegiatan();

        if ($level > 3) {
            $validator->errors()->add('kode', 'Kode kegiatan maksimal tiga tingkat.');

            return;
        }

        if ($level === 3) {
            $indukAda = RkasRefKegiatan::where('tahun', (int) $this->input('tahun'))
                ->where('kode', $this->kodeInduk())
                ->where('level', 2)
                ->exists();

            if (! $indukAda) {
                $validator->errors()->add('kode_induk', 'Komponen induk (level 2) belum ada pada tahun ini.');
            }
        }
    }

    private function periksaBarang(Validator $validator): void
    {
        $harga = (int) $this->input('harga');
        $bawah = (int) $this->input('batas_bawah');
        $atas = (int) $this->input('batas_atas');

        if ($atas > 0 && ($harga < $bawah || $harga > $atas)) {
            $validator->errors()->add('harga', 'Harga harus di antara batas bawah dan batas atas.');
        }
    }

    private function periksaSatuan(Validator $validator): void
    {
        $nama = mb_strtolower(trim((string) $this->input('nama')));

        $ada = RkasRefSatuan::query()->get(['nama'])->contains(
            fn (RkasRefSatuan $satuan): bool => mb_strtolower($satuan->nama) === $nama,
        );

        if ($ada) {
            $validator->errors()->add('nama', 'Satuan ini sudah ada.');
        }
    }
}
