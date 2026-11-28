<?php

namespace App\Modules\Flota\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Flota\Infrastructure\Models\Vehiculo;
use App\Modules\Identidad\Infrastructure\Models\Usuario;
use App\Modules\Solicitudes\Application\ReservaSalas;
use App\Modules\Solicitudes\Domain\PoliticaReserva;
use App\Modules\Solicitudes\Infrastructure\Models\SalaVelatoria;
use App\Shared\Contratos\IDisponibilidad;
use App\Shared\Infrastructure\Parametros;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Disponibilidad para el formulario de solicitud: a partir de la fecha y hora
 * del traslado calcula el intervalo comprometido y marca vehículos ocupados y
 * operarios ocupados o ausentes; con el inicio del velatorio, también las salas
 * ocupadas. Solo advierte: la validación definitiva la hace el guardado o la
 * aceptación dentro de la transacción
 */
class DisponibilidadController extends Controller
{
    public function __invoke(Request $request, Parametros $parametros, IDisponibilidad $disponibilidad, ReservaSalas $salas): JsonResponse
    {
        $request->validate(['traslado_at' => ['required', 'date'], 'velatorio_inicio' => ['nullable', 'date'], 'excluir_solicitud' => ['nullable', 'integer']]);
        [$inicio, $fin] = PoliticaReserva::intervaloComprometido(
            CarbonImmutable::parse($request->input('traslado_at'))->utc(),
            $parametros->entero('presentacion_previa_minutos'),
            $parametros->entero('duracion_traslado_horas'),
        );
        $excluir = $request->integer('excluir_solicitud');

        $vehiculosOcupados = DB::table('asignaciones')->where('activa', true)->where('externo', false)->whereNull('deleted_at')
            ->where('inicio', '<', $fin)->where('fin', '>', $inicio)->where('solicitud_id', '!=', $excluir)->pluck('vehiculo_id')->all();
        $personalOcupado = DB::table('asignacion_usuario as au')->join('asignaciones as a', 'a.id', '=', 'au.asignacion_id')
            ->where('au.activa', true)->whereNull('au.deleted_at')->where('au.inicio', '<', $fin)->where('au.fin', '>', $inicio)
            ->where('a.solicitud_id', '!=', $excluir)->pluck('au.usuario_id')->all();

        $operarios = Usuario::where('activo', true)->orderBy('nombre')->get()->filter(fn (Usuario $u) => $u->rolPrincipal() === 'Operario')->values();
        $ausentes = $disponibilidad->operariosAusentes($operarios->pluck('id')->all(), $inicio, $fin);

        $salasOcupadas = $request->filled('velatorio_inicio') ? $salas->ocupadas(
            CarbonImmutable::parse($request->input('velatorio_inicio')),
            CarbonImmutable::parse($request->input('traslado_at')),
            $excluir,
        ) : collect();

        return response()->json([
            'salas' => SalaVelatoria::orderBy('id')->get()
                ->map(fn (SalaVelatoria $s) => ['id' => $s->id, 'lugar' => $s->lugar, 'estado' => $s->estado, 'ocupada_por' => $salasOcupadas[$s->id] ?? null]),
            'inicio' => $inicio,
            'fin' => $fin,
            'vehiculos' => Vehiculo::where('activo', true)->orderBy('origen')->orderBy('nombre')->get(['id', 'nombre', 'tipo', 'patente', 'origen', 'proveedor_externo'])
                ->map(fn ($v) => [...$v->toArray(), 'ocupado' => $v->origen === 'propio' && in_array($v->id, $vehiculosOcupados)]),
            'operarios' => $operarios->map(fn (Usuario $u) => [
                'id' => $u->id,
                'nombre' => $u->nombre,
                'ocupado' => in_array($u->id, $personalOcupado),
                'ausente' => in_array($u->nombre, $ausentes),
            ]),
        ]);
    }
}
