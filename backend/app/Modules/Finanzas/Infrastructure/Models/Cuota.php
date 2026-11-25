<?php

namespace App\Modules\Finanzas\Infrastructure\Models;

use App\Shared\Infrastructure\ModeloBase;

class Cuota extends ModeloBase
{
    protected $table = 'cuotas';

    protected $fillable = ['expediente_id', 'numero', 'monto', 'vencimiento'];

    protected $casts = ['monto' => 'integer', 'vencimiento' => 'date:Y-m-d'];
}
