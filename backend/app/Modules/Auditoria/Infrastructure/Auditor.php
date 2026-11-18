<?php

namespace App\Modules\Auditoria\Infrastructure;

use App\Modules\Auditoria\Infrastructure\Models\Auditoria;
use App\Shared\Contratos\IAuditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class Auditor implements IAuditoria
{
    /** Campos que nunca se copian a la bitácora */
    private const SENSIBLES = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    public function registrar(string $accion, string $entidad, int|string|null $entidadId = null, ?array $antes = null, ?array $despues = null, ?string $motivo = null): void
    {
        Auditoria::create([
            'usuario_id' => Auth::id(),
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId !== null ? (string) $entidadId : null,
            'antes' => $this->limpiar($antes),
            'despues' => $this->limpiar($despues),
            'motivo' => $motivo,
            'ip' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
            'fecha_utc' => now(),
        ]);
    }

    private function limpiar(?array $datos): ?array
    {
        return $datos === null ? null : array_diff_key($datos, array_flip(self::SENSIBLES));
    }
}
