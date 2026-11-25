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

    protected $casts = ['monto' => 'integer', 'fecha' => 'date:Y-m-d'];

    /** @return BelongsTo<Sucursal, $this> */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /** @return BelongsTo<Proveedor, $this> */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
}
