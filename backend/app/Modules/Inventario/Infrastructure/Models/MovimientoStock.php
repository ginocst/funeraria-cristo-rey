<?php

namespace App\Modules\Inventario\Infrastructure\Models;

use App\Shared\Infrastructure\Trazable;
use Illuminate\Database\Eloquent\Model;

/** Libro inmutable de entradas y salidas (sin borrado lógico: nunca cambia) */
class MovimientoStock extends Model
{
    use Trazable;

    protected $table = 'movimientos_stock';

    protected $fillable = ['producto_id', 'solicitud_id', 'proveedor_id', 'tipo', 'cantidad', 'stock_resultante', 'motivo', 'documento'];
}
