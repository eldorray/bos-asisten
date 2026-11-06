<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TataLetakBerkas;
use App\Models\Foto;
use App\Models\JenisKegiatan;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimpanJenisKegiatanRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $kataKunci = $this->input('kata_kunci') ?? [];

        if (is_string($kataKunci)) {
            $kataKunci = explode(',', $kataKunci);
        }

        if (is_array($kataKunci)) {
            $this->merge(['kata_kunci' => array_values(array_unique(array_filter(
                array_map(fn (mixed $kata): string => is_string($kata) ? trim($kata) : '', $kataKunci),
                fn (string $kata): bool => $kata !== '',
            )))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_kegiatans', 'nama')->ignore($this->jenisSaatIni())],
            'kata_kunci' => ['present', 'array'],
            'kata_kunci.*' => ['string', 'max:100'],
            'berkas' => ['present', 'array'],
            'berkas.*.id' => ['nullable', 'integer'],
            'berkas.*.nama' => [
                'required', 'string', 'max:255', 'distinct:ignore_case',
                fn (string $atribut, mixed $nilai, Closure $gagal) => is_string($nilai) && mb_strtolower(trim($nilai)) === 'kuitansi'
                    ? $gagal('Kuitansi sudah otomatis menjadi berkas pertama.')
                    : null,
            ],
            'berkas.*.tata_letak' => ['required', Rule::enum(TataLetakBerkas::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('berkas') || ! is_array($this->input('berkas'))) {
                return;
            }

            $jenis = $this->jenisSaatIni();
            $milik = $jenis === null ? collect() : $jenis->jenisBerkas()->get()->keyBy('id');
            $dikirim = [];

            foreach ($this->input('berkas') as $i => $berkas) {
                $id = is_array($berkas) ? ($berkas['id'] ?? null) : null;

                if ($id === null || $id === '') {
                    continue;
                }

                if (! $milik->has((int) $id)) {
                    $validator->errors()->add("berkas.$i.id", 'Berkas ini bukan milik jenis kegiatan yang sedang diubah.');

                    continue;
                }

                $dikirim[] = (int) $id;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($milik->except($dikirim) as $berkas) {
                $jumlahFoto = Foto::where('jenis_berkas_id', $berkas->id)->count();

                if ($jumlahFoto > 0) {
                    $validator->errors()->add('berkas', "Berkas \"{$berkas->nama}\" sudah punya $jumlahFoto foto dan tidak bisa dihapus.");
                }
            }
        }];
    }

    /**
     * @return array{nama: string, kata_kunci: list<string>, berkas: list<array{id?: int|null, nama: string, tata_letak: string}>}
     */
    public function dataJenis(): array
    {
        /** @var array{nama: string, kata_kunci: list<string>, berkas: list<array{id?: int|null, nama: string, tata_letak: string}>} */
        return $this->validated();
    }

    private function jenisSaatIni(): ?JenisKegiatan
    {
        $jenis = $this->route('jenis_kegiatan');

        return $jenis instanceof JenisKegiatan ? $jenis : null;
    }
}
