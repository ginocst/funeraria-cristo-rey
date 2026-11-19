<?php

namespace App\Modules\Expedientes\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;

/** Datos mínimos del fallecido */
class Difunto extends ModeloBase
{
    protected $table = 'difuntos';

    protected $fillable = ['nombre', 'rut', 'fecha_nacimiento', 'fecha_fallecimiento'];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date:Y-m-d', 'fecha_fallecimiento' => 'date:Y-m-d'];
    }
}
