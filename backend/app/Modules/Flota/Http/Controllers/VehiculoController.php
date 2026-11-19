<?php

namespace App\Modules\Flota\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Flota\Infrastructure\Models\Vehiculo;
use App\Shared\Http\CrudSimple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VehiculoController extends Controller
{
    use CrudSimple;

    protected function modelo(): string
    {
        return Vehiculo::class;
    }

    protected function buscables(): array
    {
        return ['nombre', 'patente', 'marca', 'proveedor_externo'];
    }

    protected function consulta(Request $request): Builder
    {
        $q = Vehiculo::query();
        foreach (['tipo', 'origen'] as $campo) {
            if ($request->filled($campo)) {
                $q->where($campo, $request->query($campo));
            }
        }

        return $q;
    }

    protected function reglas(Request $request, $registro = null): array
    {
        if ($request->filled('patente')) {
            $request->merge(['patente' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $request->input('patente')))]);
        }

        return [
            'nombre' => ['required', 'string', 'max:80'],
            'tipo' => ['required', Rule::in(['carroza', 'instalacion', 'apoyo'])],
            'patente' => ['nullable', 'string', 'max:10', Rule::unique('vehiculos', 'patente')->ignore($registro?->id)->whereNull('deleted_at')],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'anio' => ['nullable', 'integer', 'between:1950,2100'],
            'origen' => ['required', Rule::in(['propio', 'externo'])],
            'proveedor_externo' => ['nullable', 'required_if:origen,externo', 'string', 'max:160'],
            'activo' => ['sometimes', 'boolean'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Agenda: intervalos comprometidos por vehículo (asignaciones activas) */
    public function agenda(Request $request): JsonResponse
    {
        $desde = $request->date('desde') ?? now()->startOfWeek();
        $hasta = $request->date('hasta') ?? now()->addWeeks(2);

        $filas = DB::table('asignaciones as a')
            ->join('vehiculos as v', 'v.id', '=', 'a.vehiculo_id')
            ->join('solicitudes as s', 's.id', '=', 'a.solicitud_id')
            ->join('expedientes as e', 'e.id', '=', 's.expediente_id')
            ->where('a.activa', true)->whereNull('a.deleted_at')
            ->where('a.inicio', '<', $hasta)->where('a.fin', '>', $desde)
            ->orderBy('a.inicio')
            ->get(['a.id', 'a.vehiculo_id', 'v.nombre as vehiculo', 'v.origen', 'a.inicio', 'a.fin', 's.id as solicitud_id', 's.estado', 'e.codigo', 's.lugar_sepultura']);

        return response()->json($filas);
    }
}
