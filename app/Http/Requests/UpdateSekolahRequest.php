<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSekolahRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'sebutan' => ['sometimes', 'required', 'string', 'max:50'],
            'desa_kecamatan' => ['sometimes', 'required', 'string', 'max:255'],
            'kabupaten' => ['sometimes', 'required', 'string', 'max:255'],
            'provinsi' => ['sometimes', 'required', 'string', 'max:255'],
            'kode_bukti' => ['sometimes', 'required', 'string', 'max:20'],
            'nama_kepala' => ['required', 'string', 'max:255'],
            'nama_bendahara' => ['required', 'string', 'max:255'],
            'tahun' => ['required_with:nomor_awal', 'nullable', 'integer', 'between:2000,2100'],
            'nomor_awal' => ['nullable', 'integer', 'min:1', 'max:999999'],
        ];
    }
}
