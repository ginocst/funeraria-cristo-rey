<?php

namespace App\Modules\Flota\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;

class Vehiculo extends ModeloBase
{
    protected $table = 'vehiculos';

    protected $fillable = ['nombre', 'tipo', 'patente', 'marca', 'modelo', 'anio', 'origen', 'proveedor_externo', 'activo', 'notas'];

    protected $casts = ['activo' => 'boolean', 'anio' => 'integer'];
}
