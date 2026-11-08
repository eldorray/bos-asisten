<?php

declare(strict_types=1);

namespace App\Http\Requests\Rkas;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePengaturanRkasRequest extends FormRequest
{
    private const DAFTAR = ['kode_honor', 'kode_buku', 'kode_sarpras', 'rekening_sarpras_lain'];

    protected function prepareForValidation(): void
    {
        $gabungan = [];

        foreach (self::DAFTAR as $kunci) {
            $nilai = $this->input($kunci) ?? [];

            if (is_string($nilai)) {
                $nilai = explode(',', $nilai);
            }

            if (is_array($nilai)) {
                $gabungan[$kunci] = array_values(array_unique(array_filter(
                    array_map(fn (mixed $kata): string => is_string($kata) ? trim($kata) : '', $nilai),
                    fn (string $kata): bool => $kata !== '',
                )));
            }
        }

        foreach (['kuota_pergeseran', 'kuota_perubahan'] as $kunci) {
            if ($this->input($kunci) === '') {
                $gabungan[$kunci] = null;
            }
        }

        $this->merge($gabungan);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $kodeKegiatan = 'regex:/^\d{2}(\.\d{2})*\.$/';
        $rekening = 'regex:/^\d(\.\d+){5}$/';

        return [
            'swasta' => ['required', 'boolean'],
            'persen_tahap_1' => ['required', 'integer', 'between:1,99'],
            'ambang_tahap_1' => ['required', 'integer', 'between:0,100'],
            'honor_maks_negeri' => ['required', 'integer', 'between:0,100'],
            'honor_maks_swasta' => ['required', 'integer', 'between:0,100'],
            'buku_min' => ['required', 'integer', 'between:0,100'],
            'sarpras_maks' => ['required', 'integer', 'between:0,100'],
            'kode_honor' => ['present', 'array'],
            'kode_honor.*' => ['string', $kodeKegiatan],
            'rekening_honor' => ['required', 'string', $rekening],
            'kode_buku' => ['present', 'array'],
            'kode_buku.*' => ['string', $kodeKegiatan],
            'kode_sarpras' => ['present', 'array'],
            'kode_sarpras.*' => ['string', $kodeKegiatan],
            'rekening_sarpras_lain' => ['present', 'array'],
            'rekening_sarpras_lain.*' => ['string', $rekening],
            'kuota_pergeseran' => ['nullable', 'integer', 'min:0'],
            'kuota_perubahan' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dataPengaturan(): array
    {
        return $this->validated();
    }
}
