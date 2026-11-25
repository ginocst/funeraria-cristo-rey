<?php

namespace App\Modules\Flota\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Flota\Infrastructure\Models\Asignacion;
use App\Shared\Infrastructure\Parametros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Itinerario del operario: solo los servicios a los que está asignado,
 * sin montos ni datos del cliente
 */
class ItinerarioController extends Controller
{
    public function index(Request $request, Parametros $parametros): JsonResponse
    {
        $usuarioId = $request->user()->id;
        $asignaciones = Asignacion::with(['vehiculo:id,nombre,patente,origen', 'equipo:id,nombre', 'solicitud.expediente.difunto:id,nombre', 'solicitud.sucursal:id,nombre'])
            ->where('activa', true)
            ->where('fin', '>=', now()->subDay())
            ->whereHas('equipo', fn ($q) => $q->where('usuarios.id', $usuarioId))
            ->orderBy('inicio')
            ->get();

        return response()->json($asignaciones->map(fn (Asignacion $a) => $this->presentar($a, $parametros)));
    }

    /** Detalle de una asignación: el operario solo puede ver las propias (si no, 403) */
    public function show(Request $request, int $id, Parametros $parametros): JsonResponse
    {
        $a = Asignacion::with(['vehiculo:id,nombre,patente,origen', 'equipo:id,nombre', 'solicitud.expediente.difunto:id,nombre', 'solicitud.sucursal:id,nombre'])->findOrFail($id);
        $usuario = $request->user();
        if (! $usuario->tienePermiso('calendario.ver') && ! $a->incluye($usuario->id)) {
            abort(403, 'No tiene acceso a esta asignación.');
        }

        return response()->json($this->presentar($a, $parametros));
    }

    private function presentar(Asignacion $a, Parametros $parametros): array
    {
        $s = $a->solicitud;

        return [
            'id' => $a->id,
            'codigo' => $s->expediente->codigo,
            'difunto' => $s->expediente->difunto->nombre,
            'sucursal' => $s->sucursal->nombre,
            'velatorio_inicio' => $s->velatorio_inicio,
            'lugar_velatorio' => $s->lugar_velatorio,
            'traslado_at' => $s->traslado_at,
            'presentacion_at' => $s->traslado_at?->copy()->subMinutes($parametros->entero('presentacion_previa_minutos')),
            'lugar_sepultura' => $s->lugar_sepultura,
            'sepultura_detalle' => $s->sepultura_detalle,
            'estado' => $s->estado,
            'vehiculo' => $a->vehiculo,
            'equipo' => $a->equipo->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre, 'funcion' => $u->pivot->funcion]),
        ];
    }
}
