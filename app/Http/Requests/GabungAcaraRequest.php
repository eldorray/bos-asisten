<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GabungAcaraRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kegiatan_ids' => ['required', 'array', 'min:2'],
            'kegiatan_ids.*' => ['integer', 'distinct', Rule::exists('kegiatans', 'id')],
            'nama' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kegiatan_ids.min' => 'Pilih minimal dua baris untuk digabung.',
            'nama.required' => 'Isi nama acaranya.',
        ];
    }
}
