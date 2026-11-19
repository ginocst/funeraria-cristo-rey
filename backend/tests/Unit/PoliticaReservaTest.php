<?php

use App\Modules\Solicitudes\Domain\EstadoSolicitud;
use App\Modules\Solicitudes\Domain\PoliticaReserva;
use App\Shared\Domain\ReglaNegocioException;

$ahora = new DateTimeImmutable('2026-11-09 12:00:00');

it('exige la anticipación mínima del traslado', function () use ($ahora) {
    PoliticaReserva::validarAnticipacion($ahora->modify('+23 hours'), $ahora, 24);
})->throws(ReglaNegocioException::class, '24 horas');

it('acepta un traslado con anticipación suficiente', function () use ($ahora) {
    PoliticaReserva::validarAnticipacion($ahora->modify('+24 hours'), $ahora, 24);
    expect(true)->toBeTrue();
});

it('exige que el traslado quede dentro de la duración del servicio', function () use ($ahora) {
    PoliticaReserva::validarDuracion($ahora, $ahora->modify('+49 hours'), 48);
})->throws(ReglaNegocioException::class, '48 horas');

it('rechaza un traslado anterior al velatorio', function () use ($ahora) {
    PoliticaReserva::validarDuracion($ahora, $ahora->modify('-1 hour'), 48);
})->throws(ReglaNegocioException::class);

it('calcula el abono mínimo redondeando hacia arriba', function () {
    expect(PoliticaReserva::abonoMinimoRequerido(1355001, 50))->toBe(677501)
        ->and(PoliticaReserva::abonoMinimoCumplido(1000000, 499999, 50))->toBeFalse()
        ->and(PoliticaReserva::abonoMinimoCumplido(1000000, 500000, 50))->toBeTrue();
});

it('compromete vehículo y equipo desde la presentación hasta el término estimado', function () {
    [$inicio, $fin] = PoliticaReserva::intervaloComprometido(new DateTimeImmutable('2026-11-10 15:00'), 60, 3);
    expect($inicio->format('H:i'))->toBe('14:00')->and($fin->format('H:i'))->toBe('18:00');
});

it('solo permite las transiciones del diagrama de estados', function () {
    expect(EstadoSolicitud::EnRevision->puedeTransicionarA(EstadoSolicitud::Aceptada))->toBeTrue()
        ->and(EstadoSolicitud::ConObservaciones->puedeTransicionarA(EstadoSolicitud::Aceptada))->toBeFalse()
        ->and(EstadoSolicitud::Ejecutada->puedeTransicionarA(EstadoSolicitud::Cancelada))->toBeFalse()
        ->and(EstadoSolicitud::Cancelada->esTerminal())->toBeTrue()
        ->and(EstadoSolicitud::Aceptada->comprometeRecursos())->toBeTrue();
});
