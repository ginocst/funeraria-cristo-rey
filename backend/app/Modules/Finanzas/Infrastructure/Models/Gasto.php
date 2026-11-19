<?php

namespace App\Modules\Finanzas\Infrastructure\Models;

use App\Modules\Identidad\Infrastructure\Models\Sucursal;
use App\Modules\Inventario\Infrastructure\Models\Proveedor;
use App\Shared\Infrastructure\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gasto extends ModeloBase
{
    protected $table = 'gastos';

    protected $fillable = ['sucursal_id', 'proveedor_id', 'categoria', 'descripcion', 'monto', 'fecha'];

    protected function casts(): array
    {
        return ['monto' => 'integer', 'fecha' => 'date:Y-m-d'];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
}
