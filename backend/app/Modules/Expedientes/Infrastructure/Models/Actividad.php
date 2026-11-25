<?php

namespace App\Modules\Expedientes\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;

class Actividad extends ModeloBase
{
    protected $table = 'actividades';

    protected $fillable = ['expediente_id', 'tipo', 'lugar', 'inicio', 'fin', 'notas'];

    protected $casts = ['inicio' => 'datetime', 'fin' => 'datetime'];
}
