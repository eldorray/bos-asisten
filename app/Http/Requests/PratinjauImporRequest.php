<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PratinjauImporRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Pilih minimal satu file PDF.',
            'files.*.mimes' => 'File harus berupa PDF.',
            'files.*.max' => 'Ukuran file maksimal 10 MB.',
            'files.*.file' => 'File tidak berhasil diunggah.',
        ];
    }
}
