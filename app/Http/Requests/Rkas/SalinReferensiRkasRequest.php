<?php

declare(strict_types=1);

namespace App\Http\Requests\Rkas;

use Illuminate\Foundation\Http\FormRequest;

class SalinReferensiRkasRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'dari' => ['required', 'integer', 'between:2000,2100'],
            'ke' => ['required', 'integer', 'between:2000,2100', 'different:dari'],
        ];
    }
}
