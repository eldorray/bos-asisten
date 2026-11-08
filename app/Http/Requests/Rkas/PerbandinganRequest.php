<?php

declare(strict_types=1);

namespace App\Http\Requests\Rkas;

use Illuminate\Foundation\Http\FormRequest;

class PerbandinganRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'bulan' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    public function bulan(): int
    {
        return $this->filled('bulan') ? $this->integer('bulan') : now()->month;
    }
}
