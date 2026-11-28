<?php

namespace App\Modules\Flota\Application;

use App\Modules\Flota\Infrastructure\Models\Ausencia;
use App\Modules\Flota\Infrastructure\Models\SolicitudDiasLibres;
use App\Modules\Identidad\Infrastructure\Models\Usuario;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Contratos\IEventos;
use App\Shared\Domain\ReglaNegocioException;
use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Días libres: el personal los pide indicando cuántos y por qué;
 * el administrador aprueba o rechaza explicando el motivo
 */
class GestionDiasLibres
{
    public function __construct(private IEventos $eventos, private IAuditoria $auditoria) {}

    public function solicitar(Usuario $usuario, string $desde, int $dias, string $motivo): SolicitudDiasLibres
    {
        return DB::transaction(function () use ($usuario, $desde, $dias, $motivo) {
            // Dos envíos simultáneos de la misma persona no pueden saltarse la revisión de fechas
            $this->bloquear($usuario->id);
            $hasta = $this->hasta($desde, $dias);
            $this->exigirSinAusencia($usuario->id, $desde, $hasta);
            $this->exigirSinPendiente($usuario->id, $desde, $hasta);

            $solicitud = SolicitudDiasLibres::create(['usuario_id' => $usuario->id, 'desde' => $desde, 'dias' => $dias, 'motivo' => $motivo, 'estado' => 'pendiente']);
            $this->auditoria->registrar('solicitar_dias_libres', 'dias_libres', $solicitud->id, null, ['desde' => $desde, 'dias' => $dias], $motivo);
            $this->publicar('dias_libres.solicitados', $solicitud, $usuario->nombre, $dias, $hasta, $motivo);

            return $solicitud;
        });
    }

    public function aprobar(int $id, int $dias, Usuario $evaluador): SolicitudDiasLibres
    {
        return DB::transaction(function () use ($id, $dias, $evaluador) {
            $solicitud = $this->pendiente($id, $evaluador);
            if ($dias > $solicitud->dias) {
                throw new ReglaNegocioException("No puede aprobar más días de los solicitados ({$solicitud->dias}).", 'dias');
            }
            $this->bloquear($solicitud->usuario_id);
            $desde = $solicitud->desde->toDateString();
            $hasta = $this->hasta($desde, $dias);
            $this->exigirSinAusencia($solicitud->usuario_id, $desde, $hasta);
            $this->exigirSinServicios($solicitud->usuario_id, $desde, $hasta);

            $ausencia = Ausencia::create(['usuario_id' => $solicitud->usuario_id, 'tipo' => 'permiso', 'desde' => $desde, 'hasta' => $hasta, 'motivo' => $solicitud->motivo]);
            $solicitud->update(['estado' => 'aprobada', 'dias_aprobados' => $dias, 'resuelta_por' => $evaluador->id, 'resuelta_at' => now(), 'ausencia_id' => $ausencia->id]);
            $this->auditoria->registrar('aprobar_dias_libres', 'dias_libres', $id, ['estado' => 'pendiente'], ['estado' => 'aprobada', 'dias_aprobados' => $dias]);
            $this->publicar('dias_libres.aprobados', $solicitud, $solicitud->usuario->nombre, $dias, $hasta);

            return $solicitud;
        });
    }

    public function rechazar(int $id, string $motivo, Usuario $evaluador): SolicitudDiasLibres
    {
        return DB::transaction(function () use ($id, $motivo, $evaluador) {
            $solicitud = $this->pendiente($id, $evaluador);
            $solicitud->update(['estado' => 'rechazada', 'motivo_rechazo' => $motivo, 'resuelta_por' => $evaluador->id, 'resuelta_at' => now()]);
            $this->auditoria->registrar('rechazar_dias_libres', 'dias_libres', $id, ['estado' => 'pendiente'], ['estado' => 'rechazada'], $motivo);
            $this->publicar('dias_libres.rechazados', $solicitud, $solicitud->usuario->nombre, $solicitud->dias, $solicitud->hasta, $motivo);

            return $solicitud;
        });
    }

    private function pendiente(int $id, Usuario $evaluador): SolicitudDiasLibres
    {
        $solicitud = SolicitudDiasLibres::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($solicitud->estado !== 'pendiente') {
            throw ReglaNegocioException::conflicto('La solicitud ya fue resuelta.', 'resuelta');
        }
        if ($solicitud->usuario_id === $evaluador->id) {
            throw ReglaNegocioException::conflicto('No puede resolver su propia solicitud.', 'propia');
        }

        return $solicitud;
    }

    private function bloquear(int $usuarioId): void
    {
        DB::table('usuarios')->where('id', $usuarioId)->lockForUpdate()->value('id');
    }

    private function hasta(string $desde, int $dias): string
    {
        return CarbonImmutable::parse($desde)->addDays($dias - 1)->toDateString();
    }

    private function exigirSinAusencia(int $usuarioId, string $desde, string $hasta): void
    {
        if (Ausencia::where('usuario_id', $usuarioId)->where('desde', '<=', $hasta)->where('hasta', '>=', $desde)->exists()) {
            throw ReglaNegocioException::conflicto('Ya hay una ausencia registrada en esas fechas.', 'ausencia');
        }
    }

    private function exigirSinPendiente(int $usuarioId, string $desde, string $hasta): void
    {
        $cruzada = SolicitudDiasLibres::where('usuario_id', $usuarioId)->where('estado', 'pendiente')->where('desde', '<=', $hasta)->get()
            ->contains(fn(SolicitudDiasLibres $s) => $s->hasta >= $desde);
        if ($cruzada) {
            throw ReglaNegocioException::conflicto('Ya tiene una solicitud pendiente en esas fechas.', 'pendiente');
        }
    }

    /** No se puede dejar sin equipo un servicio ya confirmado: primero hay que reasignarlo */
    private function exigirSinServicios(int $usuarioId, string $desde, string $hasta): void
    {
        $zona = HoraLocal::zona();
        $inicio = CarbonImmutable::parse($desde, $zona)->startOfDay()->utc();
        $fin = CarbonImmutable::parse($hasta, $zona)->addDay()->startOfDay()->utc();
        $codigos = DB::table('asignacion_usuario as au')->join('asignaciones as a', 'a.id', '=', 'au.asignacion_id')
            ->join('solicitudes as s', 's.id', '=', 'a.solicitud_id')->join('expedientes as e', 'e.id', '=', 's.expediente_id')
            ->where('au.usuario_id', $usuarioId)->where('au.activa', true)->whereNull('au.deleted_at')
            ->where('s.estado', 'aceptada')->whereNull('s.deleted_at')
            ->where('a.inicio', '<', $fin)->where('a.fin', '>', $inicio)->orderBy('a.inicio')->pluck('e.codigo');
        if ($codigos->isNotEmpty()) {
            throw ReglaNegocioException::conflicto('La persona tiene servicios confirmados en esas fechas (' . $codigos->implode(', ') . '). Cambie el equipo antes de aprobar.', 'servicios');
        }
    }

    private function publicar(string $evento, SolicitudDiasLibres $s, string $nombre, int $dias, string $hasta, ?string $motivo = null): void
    {
        $this->eventos->publicar($evento, [
            'usuario_id' => $s->usuario_id,
            'nombre' => $nombre,
            'dias' => $dias,
            'dias_pedidos' => $s->dias,
            'desde' => $s->desde->format('d-m-Y'),
            'hasta' => CarbonImmutable::parse($hasta)->format('d-m-Y'),
            'motivo' => $motivo,
        ]);
    }
}
