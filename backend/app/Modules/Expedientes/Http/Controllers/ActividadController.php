<?php

namespace App\Modules\Expedientes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expedientes\Infrastructure\Models\Actividad;
use App\Modules\Expedientes\Infrastructure\Models\Expediente;
use App\Shared\Contratos\IAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Etapas del servicio: retiro, velatorio, misa, traslado y sepultación */
class ActividadController extends Controller
{
    public function __construct(private IAuditoria $auditoria) {}

    public function store(Request $request, int $expedienteId): JsonResponse
    {
        Expediente::findOrFail($expedienteId);
        $actividad = Actividad::create([...$request->validate($this->reglas()), 'expediente_id' => $expedienteId]);
        $this->auditoria->registrar('crear', 'actividades', $actividad->id, null, $actividad->toArray());

        return response()->json($actividad, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $actividad = Actividad::findOrFail($id);
        $antes = $actividad->toArray();
        $actividad->update($request->validate($this->reglas()));
        $this->auditoria->registrar('editar', 'actividades', $id, $antes, $actividad->toArray());

        return response()->json($actividad);
    }

    public function destroy(int $id): JsonResponse
    {
        Actividad::findOrFail($id)->delete();
        $this->auditoria->registrar('eliminar', 'actividades', $id);

        return response()->json(null, 204);
    }

    private function reglas(): array
    {
        return [
            'tipo' => ['required', Rule::in(['retiro', 'velatorio', 'misa', 'traslado', 'sepultacion', 'otra'])],
            'lugar' => ['required', 'string', 'max:200'],
            'inicio' => ['required', 'date'],
            'fin' => ['nullable', 'date', 'after:inicio'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
