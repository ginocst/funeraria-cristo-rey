<?php

namespace App\Modules\Inventario\Infrastructure;

use App\Modules\Inventario\Domain\Stock;
use App\Modules\Inventario\Infrastructure\Models\MovimientoStock;
use App\Modules\Inventario\Infrastructure\Models\Producto;
use App\Shared\Contratos\IEventos;
use App\Shared\Contratos\IStock;

/** IStock: reserva y reposición con bloqueo de fila (SELECT … FOR UPDATE) */
class ServicioStock implements IStock
{
    public function __construct(private IEventos $eventos) {}

    public function reservar(int $solicitudId, array $items): void
    {
        ksort($items); // orden fijo de bloqueo para evitar interbloqueos
        foreach ($items as $productoId => $cantidad) {
            $producto = Producto::whereKey($productoId)->lockForUpdate()->firstOrFail();
            $producto->stock = Stock::descontar($producto->stock, (int) $cantidad, $producto->nombre);
            $producto->save();
            MovimientoStock::create([
                'producto_id' => $producto->id,
                'solicitud_id' => $solicitudId,
                'tipo' => 'salida',
                'cantidad' => -$cantidad,
                'stock_resultante' => $producto->stock,
                'motivo' => 'Reserva por solicitud aceptada',
            ]);
            if (Stock::esCritico($producto->stock, $producto->umbral_minimo)) {
                $this->eventos->publicar('stock.critico', ['producto_id' => $producto->id, 'producto' => $producto->nombre, 'stock' => $producto->stock]);
            }
        }
    }

    public function liberar(int $solicitudId, array $items, string $motivo): void
    {
        ksort($items);
        foreach ($items as $productoId => $cantidad) {
            $producto = Producto::withTrashed()->whereKey($productoId)->lockForUpdate()->firstOrFail();
            $producto->stock = Stock::reponer($producto->stock, (int) $cantidad);
            $producto->save();
            MovimientoStock::create([
                'producto_id' => $producto->id,
                'solicitud_id' => $solicitudId,
                'tipo' => 'reposicion',
                'cantidad' => $cantidad,
                'stock_resultante' => $producto->stock,
                'motivo' => $motivo,
            ]);
        }
    }
}
