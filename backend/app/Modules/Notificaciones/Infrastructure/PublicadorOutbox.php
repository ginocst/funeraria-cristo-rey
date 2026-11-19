<?php

namespace App\Modules\Notificaciones\Infrastructure;

use App\Modules\Notificaciones\Infrastructure\Models\Notificacion;
use App\Shared\Contratos\IEventos;
use Illuminate\Support\Facades\Auth;

/**
 * Escribe el evento en la tabla notificaciones. Al ejecutarse dentro de la
 * transacción del caso de uso, el evento existe solo si la operación se confirmó
 */
class PublicadorOutbox implements IEventos
{
    public function publicar(string $evento, array $payload = []): void
    {
        Notificacion::create(['evento' => $evento, 'payload' => $payload, 'estado' => 'pendiente', 'created_by' => Auth::id()]);
    }
}
