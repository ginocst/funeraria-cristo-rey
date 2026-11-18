<?php

namespace App\Shared\Contratos;

/** Interfaz que ofrece Notificaciones (IEventos): escribe el evento en el outbox */
interface IEventos
{
    public function publicar(string $evento, array $payload = []): void;
}