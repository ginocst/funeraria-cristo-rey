<?php

namespace App\Modules\Flota\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Flota\Infrastructure\Models\Ausencia;
use App\Shared\Contratos\IAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ausencias del personal (vacaciones, licencias, permisos). Solo el
 * Administrador las registra, incluidas las del coordinador; el coordinador
 * las consulta y el operario ve solo las propias.
 */
class AusenciaController extends Controller
{
    public function __construct(private IAuditoria $auditoria) {}

    public function index(Request $request): JsonResponse
    {
        $q = Ausencia::with('usuario:id,nombre')->orderByDesc('desde');
        if (! $request->user()->tienePermiso('ausencias.ver')) {
            $q->where('usuario_id', $request->user()->id);
        } elseif ($request->filled('usuario_id')) {
            $q->where('usuario_id', $request->integer('usuario_id'));
        }
        if ($request->boolean('vigentes')) {
            $q->where('hasta', '>=', now(config('funeraria.zona_horaria_local'))->toDateString());
        }

        return response()->json($q->limit(300)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $ausencia = Ausencia::create($request->validate($this->reglas()));
        $this->auditoria->registrar('crear', 'ausencias', $ausencia->id, null, $ausencia->toArray());

        return response()->json($ausencia->load('usuario:id,nombre'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ausencia = Ausencia::findOrFail($id);
        $antes = $ausencia->toArray();
        $ausencia->update($request->validate($this->reglas()));
        $this->auditoria->registrar('editar', 'ausencias', $id, $antes, $ausencia->toArray());

        return response()->json($ausencia->load('usuario:id,nombre'));
    }

    public function destroy(int $id): JsonResponse
    {
        Ausencia::findOrFail($id)->delete();
        $this->auditoria->registrar('eliminar', 'ausencias', $id);

        return response()->json(null, 204);
    }

    private function reglas(): array
    {
        return [
            'usuario_id' => ['required', 'integer', Rule::exists('usuarios', 'id')->whereNull('deleted_at')],
            'tipo' => ['required', Rule::in(['vacaciones', 'licencia', 'permiso', 'otra'])],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'motivo' => ['nullable', 'string', 'max:250'],
        ];
    }
}
