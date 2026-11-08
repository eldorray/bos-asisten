<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RkasRefRekeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tahun
 * @property string $kode
 * @property string $nama
 * @property Carbon|null $kedaluwarsa
 */
#[Fillable(['tahun', 'kode', 'nama', 'kedaluwarsa'])]
class RkasRefRekening extends Model
{
    /** @use HasFactory<RkasRefRekeningFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kedaluwarsa' => 'date',
        ];
    }
}
