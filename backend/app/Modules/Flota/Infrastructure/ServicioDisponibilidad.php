<?php

namespace App\Modules\Flota\Infrastructure;

use App\Modules\Flota\Infrastructure\Models\Asignacion;
use App\Shared\Contratos\IDisponibilidad;
use App\Shared\Domain\ReglaNegocioException;
use App\Shared\Infrastructure\HoraLocal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** IDisponibilidad: valida ausencias y activa la asignación (PostgreSQL rechaza traslapes) */
class ServicioDisponibilidad implements IDisponibilidad
{
    public function activarAsignacion(int $solicitudId): void
    {
        $asignacion = Asignacion::with('vehiculo')->where('solicitud_id', $solicitudId)->lockForUpdate()->first();
        if (! $asignacion) {
            throw new ReglaNegocioException('La solicitud no tiene vehículo asignado.', 'sin_vehiculo');
        }
        if (! $asignacion->vehiculo?->activo) {
            throw ReglaNegocioException::conflicto('El vehículo seleccionado no está activo.', 'vehiculo_inactivo');
        }
        $equipo = DB::table('asignacion_usuario')->where('asignacion_id', $asignacion->id)->whereNull('deleted_at')->pluck('usuario_id')->all();
        if (count($equipo) === 0) {
            throw new ReglaNegocioException('Debe asignar al menos un operario.', 'sin_equipo');
        }
        $ausentes = $this->operariosAusentes($equipo, $asignacion->inicio, $asignacion->fin);
        if ($ausentes) {
            throw ReglaNegocioException::conflicto('Operario con ausencia registrada en ese horario: ' . implode(', ', $ausentes) . '.', 'ausencia');
        }

        try {
            $asignacion->update(['activa' => true]);
            DB::table('asignacion_usuario')->where('asignacion_id', $asignacion->id)
                ->update(['activa' => true, 'inicio' => $asignacion->inicio, 'fin' => $asignacion->fin, 'updated_at' => now()]);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23P01') {
                $vehiculo = str_contains($e->getMessage(), 'asignaciones_sin_traslape_vehiculo');
                throw ReglaNegocioException::conflicto($vehiculo
                    ? "El vehículo {$asignacion->vehiculo->nombre} ya está asignado en ese horario."
                    : 'Un operario del equipo ya está asignado a otro servicio en ese horario.', 'traslape');
            }
            throw $e;
        }
    }

    public function liberarAsignacion(int $solicitudId): void
    {
        $id = Asignacion::where('solicitud_id', $solicitudId)->value('id');
        if ($id) {
            Asignacion::whereKey($id)->update(['activa' => false]);
            DB::table('asignacion_usuario')->where('asignacion_id', $id)->update(['activa' => false, 'updated_at' => now()]);
        }
    }

    public function operariosAusentes(array $usuarioIds, \DateTimeInterface $inicio, \DateTimeInterface $fin): array
    {
        // Las ausencias se registran por día en hora local
        return DB::table('ausencias')->join('usuarios', 'usuarios.id', '=', 'ausencias.usuario_id')
            ->whereIn('ausencias.usuario_id', $usuarioIds)->whereNull('ausencias.deleted_at')
            ->where('ausencias.desde', '<=', HoraLocal::aLocal($fin)->toDateString())
            ->where('ausencias.hasta', '>=', HoraLocal::aLocal($inicio)->toDateString())
            ->distinct()->pluck('usuarios.nombre')->all();
    }
}
