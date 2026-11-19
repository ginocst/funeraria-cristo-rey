<?php

namespace App\Modules\Inventario\Domain;

use App\Shared\Domain\ReglaNegocioException;

/** Reglas del stock (sin dependencias externas: se prueban sin base de datos) */
final class Stock
{
    public static function descontar(int $actual, int $cantidad, string $producto = 'el producto'): int
    {
        if ($cantidad <= 0) {
            throw new ReglaNegocioException('La cantidad debe ser mayor que cero.', 'cantidad');
        }
        if ($actual < $cantidad) {
            throw ReglaNegocioException::conflicto("Stock insuficiente de $producto: quedan $actual y se necesitan $cantidad.", 'sin_stock');
        }

        return $actual - $cantidad;
    }

    public static function reponer(int $actual, int $cantidad): int
    {
        if ($cantidad <= 0) {
            throw new ReglaNegocioException('La cantidad debe ser mayor que cero.', 'cantidad');
        }

        return $actual + $cantidad;
    }

    public static function esCritico(int $stock, int $umbral): bool
    {
        return $stock <= $umbral;
    }
}
