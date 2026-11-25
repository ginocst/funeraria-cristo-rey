<?php

namespace App\Modules\Inventario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventario\Domain\Stock;
use App\Modules\Inventario\Infrastructure\Models\MovimientoStock;
use App\Modules\Inventario\Infrastructure\Models\Producto;
use App\Modules\Inventario\Infrastructure\Models\ProductoFoto;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Contratos\IEventos;
use App\Shared\Http\CrudSimple;
use App\Shared\Infrastructure\Parametros;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

/** Inventario común de las sucursales: urnas, ataúdes y elementos */
class ProductoController extends Controller
{
    use CrudSimple {
        store as crudStore;
    }

    public function __construct(private IAuditoria $auditoria, private IEventos $eventos) {}

    protected function modelo(): string
    {
        return Producto::class;
    }

    protected function buscables(): array
    {
        return ['nombre', 'codigo', 'color', 'material'];
    }

    protected function consulta(Request $request): Builder
    {
        $q = Producto::query()->with('proveedor:id,nombre');
        foreach (['tipo', 'proveedor_id'] as $campo) {
            if ($request->filled($campo)) {
                $q->where($campo, $request->query($campo));
            }
        }
        if ($request->boolean('criticos')) {
            $q->whereColumn('stock', '<=', 'umbral_minimo');
        }
        if ($request->filled('publico')) {
            $q->where('publico', $request->boolean('publico'));
        }

        return $q;
    }

    protected function orden(): array
    {
        return ['codigo', 'asc'];
    }

    protected function reglas(Request $request, $registro = null): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', Rule::unique('productos', 'codigo')->ignore($registro?->id)->whereNull('deleted_at')],
            'nombre' => ['required', 'string', 'max:160'],
            'tipo' => ['required', Rule::in(['ataud', 'urna', 'urna_cremacion', 'elemento'])],
            'material' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:60'],
            'medidas' => ['nullable', 'string', 'max:80'],
            'largo_cm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'ancho_cm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'alto_cm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio' => ['required', 'integer', 'min:0', 'max:100000000'],
            'umbral_minimo' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'infantil' => ['sometimes', 'boolean'],
            'publico' => ['sometimes', 'boolean'],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')->whereNull('deleted_at')],
            'atributos' => ['sometimes', 'array'],
            'stock_inicial' => [$registro ? 'prohibited' : 'nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /** El stock inicial entra como movimiento de ingreso (nunca se escribe directo) */
    public function store(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $parametros = app(Parametros::class);
            if (! $request->filled('umbral_minimo')) {
                $request->merge(['umbral_minimo' => $parametros->entero($request->boolean('infantil') ? 'umbral_stock_infantil' : 'umbral_stock')]);
            }
            $respuesta = $this->crudStore($request);
            $id = $respuesta->getData()->id;
            if (($inicial = (int) $request->input('stock_inicial', 0)) > 0) {
                $this->moverStock(Producto::find($id), $inicial, 'ingreso', 'Stock inicial', null, null);
            }

            return response()->json(Producto::with('proveedor:id,nombre')->find($id), 201);
        });
    }

    protected function prepararDatos(array $datos, $registro = null): array
    {
        unset($datos['stock_inicial']);

        return $datos;
    }

    /** Ingreso de mercadería (cualquier rol con permiso): suma stock y deja el movimiento */
    public function ingresar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')->whereNull('deleted_at')],
            'documento' => ['nullable', 'string', 'max:60'],
            'motivo' => ['nullable', 'string', 'max:250'],
        ]);
        $producto = DB::transaction(function () use ($id, $datos) {
            $producto = Producto::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->moverStock($producto, $datos['cantidad'], 'ingreso', $datos['motivo'] ?? 'Ingreso de mercadería', $datos['proveedor_id'] ?? $producto->proveedor_id, $datos['documento'] ?? null);

            return $producto;
        });
        $this->auditoria->registrar('ingreso_mercaderia', 'productos', $id, null, $datos);

        return response()->json($producto->fresh('proveedor:id,nombre'));
    }

    /** Ajuste de inventario (permiso productos.editar: Administrador y Coordinador): corrige el stock contado y deja la diferencia como movimiento */
    public function ajustar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['stock_contado' => ['required', 'integer', 'min:0'], 'motivo' => ['required', 'string', 'max:250']]);
        $producto = DB::transaction(function () use ($id, $datos) {
            $producto = Producto::whereKey($id)->lockForUpdate()->firstOrFail();
            $diferencia = $datos['stock_contado'] - $producto->stock;
            if ($diferencia !== 0) {
                $this->moverStock($producto, $diferencia, 'ajuste', $datos['motivo'], null, null);
            }

            return $producto;
        });
        $this->auditoria->registrar('ajuste_stock', 'productos', $id, null, $datos);

        return response()->json($producto->fresh());
    }

    /** Merma: urnas o elementos que se quiebran o dañan. Salen del stock disponible y queda el movimiento con su motivo (permiso productos.editar) */
    public function merma(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            'motivo' => ['required', 'string', 'min:5', 'max:250'],
        ]);
        $producto = DB::transaction(function () use ($id, $datos) {
            $producto = Producto::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->moverStock($producto, -$datos['cantidad'], 'merma', $datos['motivo'], null, null);

            return $producto;
        });
        $this->auditoria->registrar('merma_stock', 'productos', $id, null, $datos);

        return response()->json($producto->fresh());
    }

    public function movimientos(Request $request): JsonResponse
    {
        $q = MovimientoStock::query()
            ->join('productos', 'productos.id', '=', 'movimientos_stock.producto_id')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'movimientos_stock.created_by')
            ->leftJoin('proveedores', 'proveedores.id', '=', 'movimientos_stock.proveedor_id')
            ->select('movimientos_stock.*', 'productos.codigo', 'productos.nombre as producto', 'usuarios.nombre as usuario', 'proveedores.nombre as proveedor')
            ->orderByDesc('movimientos_stock.id');
        if ($request->filled('producto_id')) {
            $q->where('movimientos_stock.producto_id', $request->integer('producto_id'));
        }
        if ($request->filled('tipo')) {
            $q->where('movimientos_stock.tipo', $request->query('tipo'));
        }

        return response()->json($q->paginate(min((int) $request->query('por_pagina', 30), 200)));
    }

    /** Foto del catálogo: se recodifica (elimina contenido oculto y metadatos) y se guarda en catalogo/ */
    public function foto(Request $request, int $id): JsonResponse
    {
        $request->validate(['foto' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp']]);
        $producto = Producto::findOrFail($id);
        $imagen = Image::decode($request->file('foto'))->scaleDown(width: 1000);
        $ruta = Str::slug($producto->codigo).'-'.Str::random(8).'.webp';
        Storage::disk('catalogo')->put($ruta, $imagen->encodeUsingFormat(Format::WEBP, quality: 82)->toString());
        $anterior = $producto->foto_ruta;
        $producto->foto_ruta = $ruta;
        $producto->save();
        if ($anterior) {
            Storage::disk('catalogo')->delete($anterior);
        }
        $this->auditoria->registrar('foto_producto', 'productos', $id);

        return response()->json($producto->fresh());
    }

    /** Fotos del modelo para el carrusel (la principal y las adicionales) */
    public function fotos(int $id): JsonResponse
    {
        return response()->json(Producto::with('fotos')->findOrFail($id)->galeria());
    }

    /** Foto adicional (vista abierta, lateral u otra): máximo 8, se recodifica igual que la principal */
    public function agregarFoto(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'foto' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
            'vista' => ['required', Rule::in(['abierta', 'lateral', 'otra'])],
        ]);
        $producto = Producto::findOrFail($id);
        if ($producto->fotos()->count() >= 8) {
            throw ValidationException::withMessages(['foto' => 'Cada modelo admite hasta 8 fotos adicionales.']);
        }
        $imagen = Image::decode($request->file('foto'))->scaleDown(width: 1000);
        $ruta = Str::slug($producto->codigo).'-'.$datos['vista'].'-'.Str::random(8).'.webp';
        Storage::disk('catalogo')->put($ruta, $imagen->encodeUsingFormat(Format::WEBP, quality: 82)->toString());
        $foto = $producto->fotos()->create(['ruta' => $ruta, 'vista' => $datos['vista'], 'orden' => (int) $producto->fotos()->max('orden') + 1]);
        $this->auditoria->registrar('foto_producto', 'productos', $id, null, ['vista' => $datos['vista']]);

        return response()->json($foto, 201);
    }

    public function quitarFoto(int $id, int $fotoId): JsonResponse
    {
        $foto = ProductoFoto::where('producto_id', $id)->findOrFail($fotoId);
        $foto->delete();
        Storage::disk('catalogo')->delete($foto->ruta);
        $this->auditoria->registrar('quitar_foto_producto', 'productos', $id, null, ['foto_id' => $fotoId]);

        return response()->json(null, 204);
    }

    private function moverStock(Producto $producto, int $cantidad, string $tipo, string $motivo, ?int $proveedorId, ?string $documento): void
    {
        $producto->stock = $cantidad > 0 ? Stock::reponer($producto->stock, $cantidad) : Stock::descontar($producto->stock, -$cantidad, $producto->nombre);
        $producto->save();
        MovimientoStock::create([
            'producto_id' => $producto->id,
            'proveedor_id' => $proveedorId,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'stock_resultante' => $producto->stock,
            'motivo' => $motivo,
            'documento' => $documento,
        ]);
        if ($cantidad < 0 && Stock::esCritico($producto->stock, $producto->umbral_minimo)) {
            $this->eventos->publicar('stock.critico', ['producto_id' => $producto->id, 'producto' => $producto->nombre, 'stock' => $producto->stock]);
        }
    }
}
