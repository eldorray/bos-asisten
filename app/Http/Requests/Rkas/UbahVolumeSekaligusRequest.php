<?php

declare(strict_types=1);

namespace App\Http\Requests\Rkas;

use App\Actions\Rkas\PesanRkas;
use App\Enums\SistemAnggaran;
use App\Models\RkasKertasKerja;
use Illuminate\Foundation\Http\FormRequest;

class UbahVolumeSekaligusRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $kertasKerja = $this->route('kertasKerja');
        abort_unless($kertasKerja instanceof RkasKertasKerja && $kertasKerja->sekolah->sistem === SistemAnggaran::Arkas, 404);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'baris' => ['required', 'array', 'min:1', 'max:500'],
            'baris.*' => ['required', 'uuid', 'distinct'],
            'bulan' => ['required', 'array', 'min:1'],
            'bulan.*' => ['required', 'integer', 'between:1,12', 'distinct'],
            'volume' => ['required', 'integer', 'min:1', 'max:999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'baris.required' => PesanRkas::WAJIB,
            'bulan.required' => PesanRkas::VOLUME_KOSONG,
            'volume.required' => PesanRkas::WAJIB,
            'volume.integer' => PesanRkas::VOLUME_MAKS,
            'volume.min' => PesanRkas::WAJIB,
            'volume.max' => PesanRkas::VOLUME_MAKS,
        ];
    }

    /**
     * @return list<string>
     */
    public function uuidBaris(): array
    {
        return array_values(array_map(strval(...), (array) $this->validated('baris')));
    }

    /**
     * @return list<int>
     */
    public function bulan(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('bulan')));
    }
}
