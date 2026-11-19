<?php

namespace App\Modules\Finanzas\Domain;

use App\Shared\Domain\ReglaNegocioException;

/** Reglas de pagos y saldos: sin dependencias externas */
final class ReglasPago
{
    public static function validarAbono(int $monto, int $saldo): void
    {
        if ($monto <= 0) {
            throw new ReglaNegocioException('El importe debe ser mayor que cero.', 'importe');
        }
        if ($saldo <= 0) {
            throw new ReglaNegocioException('El expediente no tiene saldo por pagar. Registre primero la solicitud del servicio.', 'sin_saldo');
        }
        if ($monto > $saldo) {
            throw new ReglaNegocioException('El importe supera el saldo pendiente ($' . number_format($saldo, 0, ',', '.') . ').', 'importe');
        }
    }

    public static function saldo(int $total, array $pagos): int
    {
        return $total - array_sum($pagos);
    }
}
