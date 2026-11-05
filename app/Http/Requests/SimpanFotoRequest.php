<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SimpanFoto;
use App\Models\Kegiatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanFotoRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $kegiatan = $this->route('kegiatan');

        return [
            'jenis_berkas_id' => [
                'required', 'integer',
                Rule::exists('jenis_berkas', 'id')->where(
                    'jenis_kegiatan_id',
                    $kegiatan instanceof Kegiatan ? $kegiatan->jenis_kegiatan_id : 0,
                ),
            ],
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Pilih foto dulu.',
            'foto.file' => SimpanFoto::PESAN_FORMAT,
            'foto.mimes' => SimpanFoto::PESAN_FORMAT,
            'foto.uploaded' => 'Foto gagal terunggah. Coba lagi, atau pakai foto yang lebih kecil.',
            'foto.max' => 'Foto lebih dari 15 MB. Kecilkan dulu lalu unggah lagi.',
            'jenis_berkas_id.exists' => 'Jenis berkas ini tidak dipakai kegiatan ini.',
        ];
    }
}
