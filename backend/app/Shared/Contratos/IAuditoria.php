<?php

namespace App\Shared\Contratos;

/** Registro en la bitácora inmutable (módulo Auditoría) */
interface IAuditoria
{
    public function registrar(string $accion, string $entidad, int|string|null $entidadId = null, ?array $antes = null, ?array $despues = null, ?string $motivo = null): void;
}
