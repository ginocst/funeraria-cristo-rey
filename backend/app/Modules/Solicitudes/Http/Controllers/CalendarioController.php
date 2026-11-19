<?php

namespace App\Modules\Solicitudes\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Calendario de servicios: velatorios, traslados y actividades. */
class CalendarioController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $desde = $request->date('desde') ?? now()->startOfMonth()->subWeek();
        $hasta = $request->date('hasta') ?? now()->endOfMonth()->addWeek();

        $servicios = DB::table('solicitudes as s')
            ->join('expedientes as e', 'e.id', '=', 's.expediente_id')
            ->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->leftJoin('asignaciones as a', 'a.solicitud_id', '=', 's.id')
            ->leftJoin('vehiculos as v', 'v.id', '=', 'a.vehiculo_id')
            ->whereNull('s.deleted_at')->whereIn('s.estado', ['en_revision', 'con_observaciones', 'aceptada', 'ejecutada'])
            ->where('s.velatorio_inicio', '<', $hasta)->where('s.traslado_at', '>', $desde)
            ->get([
                's.id',
                's.estado',
                's.velatorio_inicio',
                's.traslado_at',
                's.lugar_velatorio',
                's.lugar_sepultura',
                'e.id as expediente_id',
                'e.codigo',
                'd.nombre as difunto',
                'v.nombre as vehiculo',
                'a.fin'
            ]);

        $eventos = [];
        foreach ($servicios as $s) {
            $base = ['solicitud_id' => $s->id, 'expediente_id' => $s->expediente_id, 'estado' => $s->estado, 'codigo' => $s->codigo, 'difunto' => $s->difunto];
            $eventos[] = [...$base, 'id' => "v{$s->id}", 'tipo' => 'velatorio', 'titulo' => "Velatorio: {$s->difunto}", 'inicio' => $s->velatorio_inicio, 'fin' => $s->traslado_at, 'lugar' => $s->lugar_velatorio];
            $eventos[] = [...$base, 'id' => "t{$s->id}", 'tipo' => 'traslado', 'titulo' => "Traslado: {$s->difunto}", 'inicio' => $s->traslado_at, 'fin' => $s->fin ?? $s->traslado_at, 'lugar' => $s->lugar_sepultura, 'vehiculo' => $s->vehiculo];
        }

        $actividades = DB::table('actividades as ac')->join('expedientes as e', 'e.id', '=', 'ac.expediente_id')->join('difuntos as d', 'd.id', '=', 'e.difunto_id')
            ->whereNull('ac.deleted_at')->whereIn('ac.tipo', ['retiro', 'misa', 'otra'])->where('ac.inicio', '<', $hasta)->where('ac.inicio', '>', $desde)
            ->get(['ac.id', 'ac.tipo', 'ac.lugar', 'ac.inicio', 'ac.fin', 'e.id as expediente_id', 'e.codigo', 'd.nombre as difunto']);
        foreach ($actividades as $a) {
            $eventos[] = [
                'id' => "a{$a->id}",
                'tipo' => $a->tipo,
                'titulo' => ($a->tipo === 'otra' ? 'Actividad' : ucfirst($a->tipo)) . ": {$a->difunto}",
                'inicio' => $a->inicio,
                'fin' => $a->fin,
                'lugar' => $a->lugar,
                'expediente_id' => $a->expediente_id,
                'codigo' => $a->codigo,
                'estado' => 'aceptada'
            ];
        }

        return response()->json($eventos);
    }
}
