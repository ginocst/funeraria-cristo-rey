<?php

namespace App\Modules\Finanzas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finanzas\Infrastructure\Models\Gasto;
use App\Shared\Http\CrudSimple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Gastos del negocio para el control mensual (solo Administrador) */
class GastoController extends Controller
{
    use CrudSimple;

    public const CATEGORIAS = ['Combustible', 'Mantención', 'Flores y arreglos', 'Insumos', 'Remuneraciones', 'Arriendo de carroza', 'Servicios básicos', 'Otros'];

    public function categorias(): JsonResponse
    {
        return response()->json(self::CATEGORIAS);
    }

    protected function modelo(): string
    {
        return Gasto::class;
    }

    protected function buscables(): array
    {
        return ['descripcion', 'categoria'];
    }

    protected function orden(): array
    {
        return ['fecha', 'desc'];
    }

    protected function consulta(Request $request): Builder
    {
        $q = Gasto::query()->with(['sucursal:id,nombre', 'proveedor:id,nombre']);
        if ($request->filled('mes')) {
            $inicio = $request->query('mes') . '-01';
            $q->where('fecha', '>=', $inicio)->where('fecha', '<', date('Y-m-d', strtotime("$inicio +1 month")));
        }
        if ($request->filled('categoria')) {
            $q->where('categoria', $request->query('categoria'));
        }

        return $q;
    }

    protected function reglas(Request $request, $registro = null): array
    {
        return [
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')->whereNull('deleted_at')],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')->whereNull('deleted_at')],
            'categoria' => ['required', Rule::in(self::CATEGORIAS)],
            'descripcion' => ['required', 'string', 'max:250'],
            'monto' => ['required', 'integer', 'min:1', 'max:100000000'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
