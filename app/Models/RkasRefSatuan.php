<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PakaiUuid;
use Database\Factories\RkasRefSatuanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string $nama
 */
#[Fillable(['nama'])]
class RkasRefSatuan extends Model
{
    /** @use HasFactory<RkasRefSatuanFactory> */
    use HasFactory, PakaiUuid;
}
