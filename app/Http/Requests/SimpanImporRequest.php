<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimpanImporRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tokens' => ['required', 'array', 'min:1', 'max:20'],
            'tokens.*' => ['required', 'uuid'],
        ];
    }
}
