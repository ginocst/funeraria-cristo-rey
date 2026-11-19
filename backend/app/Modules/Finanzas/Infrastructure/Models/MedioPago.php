<?php

namespace App\Modules\Finanzas\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;

class MedioPago extends ModeloBase
{
    protected $table = 'medios_pago';

    protected $fillable = ['nombre', 'requiere_referencia', 'activo'];

    protected function casts(): array
    {
        return ['requiere_referencia' => 'boolean', 'activo' => 'boolean'];
    }
}
