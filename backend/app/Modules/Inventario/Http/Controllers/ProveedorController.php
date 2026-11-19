<?php

namespace App\Modules\Inventario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventario\Infrastructure\Models\Producto;
use App\Modules\Inventario\Infrastructure\Models\Proveedor;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Domain\Rut;
use App\Shared\Http\CrudSimple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    use CrudSimple;

    protected function modelo(): string
    {
        return Proveedor::class;
    }

    protected function buscables(): array
    {
        return ['nombre', 'rut', 'contacto', 'rubro'];
    }

    protected function consulta(Request $request): Builder
    {
        $q = Proveedor::query()->withCount('productos');
        if ($request->filled('rubro')) {
            $q->where('rubro', $request->query('rubro'));
        }

        return $q;
    }

    protected function reglas(Request $request, $registro = null): array
    {
        if ($request->filled('rut')) {
            $request->merge(['rut' => Rut::normalizar($request->input('rut'))]);
        }

        return [
            'nombre' => ['required', 'string', 'max:160'],
            'rut' => [
                'nullable',
                'string',
                'max:12',
                fn($a, $v, $f) => $v && ! Rut::formatoValido($v) ? $f('El RUT debe tener el formato 12345678-9.') : null,
                Rule::unique('proveedores', 'rut')->ignore($registro?->id)->whereNull('deleted_at')
            ],
            'rubro' => ['required', 'string', 'max:80'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /** Proveedor con sus productos asociados */
    public function show(int $id): JsonResponse
    {
        return response()->json(Proveedor::with('productos:id,proveedor_id,codigo,nombre,tipo,stock,precio')->findOrFail($id));
    }

    /** Asocia un producto del inventario a este proveedor (un producto tiene un proveedor habitual) */
    public function asociarProducto(Request $request, int $id): JsonResponse
    {
        $proveedor = Proveedor::findOrFail($id);
        $datos = $request->validate(['producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->whereNull('deleted_at')]]);
        $producto = Producto::findOrFail($datos['producto_id']);
        $anterior = $producto->proveedor_id;
        $producto->update(['proveedor_id' => $proveedor->id]);
        app(IAuditoria::class)->registrar('asociar_proveedor', 'productos', $producto->id, ['proveedor_id' => $anterior], ['proveedor_id' => $proveedor->id]);

        return $this->show($id);
    }

    public function desasociarProducto(int $id, int $productoId): JsonResponse
    {
        $producto = Producto::where('proveedor_id', $id)->findOrFail($productoId);
        $producto->update(['proveedor_id' => null]);
        app(IAuditoria::class)->registrar('desasociar_proveedor', 'productos', $producto->id, ['proveedor_id' => $id], ['proveedor_id' => null]);

        return $this->show($id);
    }
}
