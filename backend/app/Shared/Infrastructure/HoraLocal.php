<?php

namespace App\Shared\Infrastructure;

use Carbon\CarbonImmutable;

/** Conversión entre UTC (base de datos) y la hora local (operación). */
final class HoraLocal
{
    public static function zona(): string
    {
        return config('funeraria.zona_horaria_local', 'America/Santiago');
    }

    public static function aLocal(?\DateTimeInterface $fecha): ?CarbonImmutable
    {
        return $fecha ? CarbonImmutable::instance($fecha)->setTimezone(self::zona()) : null;
    }

    public static function formato(?\DateTimeInterface $fecha, string $formato = 'd-m-Y H:i'): string
    {
        return $fecha ? self::aLocal($fecha)->format($formato) : '—';
    }

    public static function inicioMesUtc(string $mes): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $mes . '-01 00:00', self::zona())->utc();
    }
}