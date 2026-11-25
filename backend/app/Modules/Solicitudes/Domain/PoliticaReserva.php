<?php

namespace App\Modules\Solicitudes\Domain;

use App\Shared\Domain\ReglaNegocioException;
use DateInterval;
use DateTimeImmutable;

/**
 * Reglas de la solicitud de reserva. No depende de Laravel ni de la base de
 * datos: se prueba con pruebas unitarias rápidas
 */
final class PoliticaReserva
{
    public static function validarAnticipacion(DateTimeImmutable $traslado, DateTimeImmutable $ahora, int $horasMinimas): void
    {
        $limite = $ahora->add(new DateInterval("PT{$horasMinimas}H"));
        if ($traslado < $limite) {
            throw new ReglaNegocioException("El traslado debe programarse con al menos $horasMinimas horas de anticipación.", 'anticipacion');
        }
    }

    /** El traslado debe ocurrir después del inicio del velatorio y dentro de la duración del servicio */
    public static function validarDuracion(DateTimeImmutable $velatorioInicio, DateTimeImmutable $traslado, int $horasMaximas): void
    {
        if ($traslado <= $velatorioInicio) {
            throw new ReglaNegocioException('El traslado debe ser posterior al inicio del velatorio.', 'duracion');
        }
        if ($traslado > $velatorioInicio->add(new DateInterval("PT{$horasMaximas}H"))) {
            throw new ReglaNegocioException("El traslado debe quedar dentro de las $horasMaximas horas del servicio.", 'duracion');
        }
    }

    public static function validarEquipo(int $cantidadOperarios): void
    {
        if ($cantidadOperarios < 1) {
            throw new ReglaNegocioException('Debe asignar al menos un operario.', 'sin_equipo');
        }
    }

    public static function abonoMinimoRequerido(int $total, int $porcentaje): int
    {
        return (int) ceil($total * $porcentaje / 100);
    }

    public static function abonoMinimoCumplido(int $total, int $pagado, int $porcentaje): bool
    {
        return $pagado >= self::abonoMinimoRequerido($total, $porcentaje);
    }

    /**
     * Intervalo en que el vehículo y el equipo quedan comprometidos: desde la
     * hora de presentación (antes del traslado) hasta el término estimado
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public static function intervaloComprometido(DateTimeImmutable $traslado, int $presentacionMinutos, int $duracionHoras): array
    {
        return [
            $traslado->sub(new DateInterval("PT{$presentacionMinutos}M")),
            $traslado->add(new DateInterval('PT'.max(1, $duracionHoras).'H')),
        ];
    }
}
