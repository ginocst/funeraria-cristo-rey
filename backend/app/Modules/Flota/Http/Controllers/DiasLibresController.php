<?php

namespace App\Modules\Flota\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Flota\Application\GestionDiasLibres;
use App\Modules\Flota\Infrastructure\Models\SolicitudDiasLibres;
use App\Shared\Infrastructure\HoraLocal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solicitudes de días libres. Operarios y coordinadores piden y ven las suyas;
 * quien puede evaluarlas (Administrador) ve todas y las resuelve
 */
class DiasLibresController extends Controller
{
    public function __construct(private GestionDiasLibres $gestion) {}

    public function index(Request $request): JsonResponse
    {
        $q = SolicitudDiasLibres::with(['usuario:id,nombre', 'evaluador:id,nombre'])
            // Primero lo que espera respuesta
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")->orderByDesc('desde')->orderByDesc('id');
        if (! $request->user()->tienePermiso('dias_libres.evaluar')) {
            $q->where('usuario_id', $request->user()->id);
        }

        return response()->json($q->limit(300)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $hoy = now(HoraLocal::zona())->toDateString();
        $d = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d', "after_or_equal:$hoy"],
            'dias' => ['required', 'integer', 'between:1,30'],
            'motivo' => ['required', 'string', 'min:5', 'max:250'],
        ], ['desde.after_or_equal' => 'La fecha no puede ser anterior a hoy.']);

        return response()->json($this->gestion->solicitar($request->user(), $d['desde'], (int) $d['dias'], $d['motivo']), 201);
    }

    public function aprobar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['dias' => ['required', 'integer', 'between:1,30']]);

        return response()->json($this->gestion->aprobar($id, (int) $d['dias'], $request->user()));
    }

    public function rechazar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:250']]);

        return response()->json($this->gestion->rechazar($id, $d['motivo'], $request->user()));
    }
}
