<?php

namespace App\Modules\Identidad\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;

class Sucursal extends ModeloBase
{
    protected $table = 'sucursales';

    protected $fillable = ['nombre', 'direccion', 'telefono'];
}