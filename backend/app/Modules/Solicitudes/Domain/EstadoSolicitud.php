<?php

namespace App\Modules\Solicitudes\Domain;

/** Estados de la solicitud */
enum EstadoSolicitud: string
{
    case EnRevision = 'en_revision';
    case ConObservaciones = 'con_observaciones';
    case Aceptada = 'aceptada';
    case Ejecutada = 'ejecutada';
    case NoAplica = 'no_aplica';
    case Cancelada = 'cancelada';

    public function esTerminal(): bool
    {
        return in_array($this, [self::Ejecutada, self::NoAplica, self::Cancelada], true);
    }

    /** Solo la aceptada compromete recursos (stock, vehículo y equipo) */
    public function comprometeRecursos(): bool
    {
        return $this === self::Aceptada;
    }

    public function puedeTransicionarA(self $nuevo): bool
    {
        return in_array($nuevo, match ($this) {
            self::EnRevision => [self::ConObservaciones, self::Aceptada, self::NoAplica, self::Cancelada],
            self::ConObservaciones => [self::EnRevision, self::Cancelada],
            self::Aceptada => [self::Aceptada, self::Ejecutada, self::Cancelada],
            default => [],
        }, true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnRevision => 'En revisión',
            self::ConObservaciones => 'Con observaciones',
            self::Aceptada => 'Aceptada',
            self::Ejecutada => 'Ejecutada',
            self::NoAplica => 'No aplica',
            self::Cancelada => 'Cancelada',
        };
    }
}
