<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RkasRefSatuanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nama
 */
#[Fillable(['nama'])]
class RkasRefSatuan extends Model
{
    /** @use HasFactory<RkasRefSatuanFactory> */
    use HasFactory;
}
