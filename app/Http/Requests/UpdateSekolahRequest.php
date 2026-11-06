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
            'nama' => ['sometimes', 'required', 'string', 'max:100'],
            'sebutan' => ['sometimes', 'required', 'string', 'max:20'],
            'desa_kecamatan' => ['sometimes', 'required', 'string', 'max:100'],
            'kabupaten' => ['sometimes', 'required', 'string', 'max:100'],
            'provinsi' => ['sometimes', 'required', 'string', 'max:100'],
            'kode_bukti' => ['sometimes', 'required', 'string', 'max:20'],
            'nama_kepala' => ['required', 'string', 'max:90'],
            'nama_bendahara' => ['required', 'string', 'max:90'],
            'tahun' => ['required_with:nomor_awal', 'nullable', 'integer', 'between:2000,2100'],
            'nomor_awal' => ['nullable', 'integer', 'min:1', 'max:999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.max' => 'Nama sekolah maksimal 100 karakter agar muat di kuitansi.',
            'sebutan.max' => 'Sebutan maksimal 20 karakter agar muat di kuitansi.',
            'desa_kecamatan.max' => 'Desa/kecamatan maksimal 100 karakter agar muat di kuitansi.',
            'kabupaten.max' => 'Kabupaten maksimal 100 karakter agar muat di kuitansi.',
            'provinsi.max' => 'Provinsi maksimal 100 karakter agar muat di kuitansi.',
            'nama_kepala.max' => 'Nama kepala maksimal 90 karakter agar muat di kuitansi.',
            'nama_bendahara.max' => 'Nama bendahara maksimal 90 karakter agar muat di kuitansi.',
        ];
    }
}
