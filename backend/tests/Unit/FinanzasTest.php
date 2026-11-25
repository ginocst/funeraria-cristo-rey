<?php

use App\Modules\Finanzas\Domain\PlanCuotas;
use App\Modules\Finanzas\Domain\ReglasPago;
use App\Shared\Domain\ReglaNegocioException;

it('no permite pagos sin saldo pendiente', function () {
    ReglasPago::validarAbono(10000, 0);
})->throws(ReglaNegocioException::class, 'no tiene saldo');

it('no permite pagar más que el saldo', function () {
    ReglasPago::validarAbono(200001, 200000);
})->throws(ReglaNegocioException::class, 'supera el saldo');

it('rechaza importes cero o negativos', function () {
    ReglasPago::validarAbono(0, 1000);
})->throws(ReglaNegocioException::class);

it('reparte el saldo en cuotas iguales y deja el redondeo en la última', function () {
    $cuotas = PlanCuotas::generar(1000000, 3, new DateTimeImmutable('2026-12-10'));
    expect(array_column($cuotas, 'monto'))->toBe([333333, 333333, 333334])
        ->and(array_sum(array_column($cuotas, 'monto')))->toBe(1000000)
        ->and(array_column($cuotas, 'vencimiento'))->toBe(['2026-12-10', '2027-01-10', '2027-02-10']);
});

it('no desborda el mes en vencimientos a fin de mes', function () {
    $cuotas = PlanCuotas::generar(300000, 3, new DateTimeImmutable('2027-01-31'));
    expect(array_column($cuotas, 'vencimiento'))->toBe(['2027-01-31', '2027-02-28', '2027-03-31']);
});

it('clasifica cuotas pagadas, pendientes y atrasadas', function () {
    $hoy = new DateTimeImmutable('2026-11-09');
    expect(PlanCuotas::estado(1000, 1000, new DateTimeImmutable('2026-10-01'), $hoy))->toBe('pagada')
        ->and(PlanCuotas::estado(1000, 0, new DateTimeImmutable('2026-11-08'), $hoy))->toBe('atrasada')
        ->and(PlanCuotas::estado(1000, 500, new DateTimeImmutable('2026-11-30'), $hoy))->toBe('pendiente');

    // «Hoy» a las 00:00 local y el vencimiento a las 00:00 UTC: la cuota que vence hoy no está atrasada
    $hoyChile = new DateTimeImmutable('2026-11-16 00:00', new DateTimeZone('America/Santiago'));
    $vence = fn (string $dia) => new DateTimeImmutable($dia, new DateTimeZone('UTC'));
    expect(PlanCuotas::estado(1000, 0, $vence('2026-11-16'), $hoyChile))->toBe('pendiente')
        ->and(PlanCuotas::estado(1000, 0, $vence('2026-11-15'), $hoyChile))->toBe('atrasada');
});
