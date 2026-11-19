<?php

namespace App\Modules\Finanzas\Domain;

use App\Shared\Domain\ReglaNegocioException;
use DateTimeImmutable;

/** Pacta el saldo en cuotas mensuales iguales; la diferencia por redondeo va en la última */
final class PlanCuotas
{
    /** @return array<int, array{numero:int, monto:int, vencimiento:string}> */
    public static function generar(int $saldo, int $numeroCuotas, DateTimeImmutable $primerVencimiento): array
    {
        if ($saldo <= 0) {
            throw new ReglaNegocioException('No hay saldo pendiente para pactar en cuotas.', 'sin_saldo');
        }
        if ($numeroCuotas < 1 || $numeroCuotas > 24) {
            throw new ReglaNegocioException('El número de cuotas debe estar entre 1 y 24.', 'cuotas');
        }
        $base = intdiv($saldo, $numeroCuotas);
        $cuotas = [];
        for ($i = 1; $i <= $numeroCuotas; $i++) {
            $cuotas[] = [
                'numero' => $i,
                'monto' => $i === $numeroCuotas ? $saldo - $base * ($numeroCuotas - 1) : $base,
                'vencimiento' => self::sumarMeses($primerVencimiento, $i - 1)->format('Y-m-d'),
            ];
        }

        return $cuotas;
    }

    /** Suma meses sin desbordar: 31-01 + 1 mes = 28-02 (o 29-02), no 03-03 */
    public static function sumarMeses(DateTimeImmutable $fecha, int $meses): DateTimeImmutable
    {
        $base = $fecha->modify('first day of this month')->modify("+$meses month");
        $dia = min((int) $fecha->format('j'), (int) $base->format('t'));

        return $base->setDate((int) $base->format('Y'), (int) $base->format('n'), $dia);
    }

    public static function estado(int $monto, int $pagado, DateTimeImmutable $vencimiento, DateTimeImmutable $hoy): string
    {
        if ($pagado >= $monto) {
            return 'pagada';
        }

        // Se comparan los días (AAAA-MM-DD), no los instantes: el vencimiento viene a las 00:00 UTC y «hoy» a las 00:00 de Chile,
        // y por instantes una cuota que vence hoy aparecía como atrasada
        return $vencimiento->format('Y-m-d') < $hoy->format('Y-m-d') ? 'atrasada' : 'pendiente';
    }
}
