<?php

namespace App\Modules\Inventario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventario\Infrastructure\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo público de urnas: única ruta sin sesión y de solo lectura
 * Respuesta con campos explícitos (lista blanca): incluye el precio, pero
 * no el stock, el proveedor ni datos internos.
 */
class CatalogoPublicoController extends Controller
{
    private const CAMPOS = ['id', 'codigo', 'nombre', 'tipo', 'material', 'color', 'medidas', 'largo_cm', 'ancho_cm', 'alto_cm', 'descripcion', 'precio', 'infantil', 'foto_url'];

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'tipo' => ['nullable', Rule::in(['ataud', 'urna', 'urna_cremacion', 'elemento'])],
            'material' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:60'],
            'infantil' => ['nullable', 'boolean'],
            'precio_min' => ['nullable', 'integer', 'min:0'],
            'precio_max' => ['nullable', 'integer', 'min:0'],
            'orden' => ['nullable', Rule::in(['relevancia', 'precio_asc', 'precio_desc', 'nombre', 'recientes'])],
        ]);

        $q = Producto::query()->with('fotos')->where('publico', true);
        if (! empty($f['q'])) {
            $like = '%'.addcslashes($f['q'], '%_').'%';
            $q->where(fn ($w) => $w->where('nombre', 'ilike', $like)->orWhere('codigo', 'ilike', $like)->orWhere('descripcion', 'ilike', $like));
        }
        foreach (['tipo', 'material', 'color'] as $campo) {
            if (! empty($f[$campo])) {
                $q->where($campo, $f[$campo]);
            }
        }
        if (isset($f['infantil'])) {
            $q->where('infantil', (bool) $f['infantil']);
        }
        if (isset($f['precio_min'])) {
            $q->where('precio', '>=', $f['precio_min']);
        }
        if (isset($f['precio_max'])) {
            $q->where('precio', '<=', $f['precio_max']);
        }
        match ($f['orden'] ?? 'relevancia') {
            'precio_asc' => $q->orderBy('precio'),
            'precio_desc' => $q->orderByDesc('precio'),
            'nombre' => $q->orderBy('nombre'),
            'recientes' => $q->orderByDesc('id'),
            // Destacados: modelos de adulto primero, ataúdes, urnas y urnas de cremación, de mayor a menor precio
            default => $q->orderBy('infantil')->orderByRaw("array_position(ARRAY['ataud','urna','urna_cremacion','elemento']::varchar[], tipo::varchar)")->orderByDesc('precio'),
        };

        return response()->json($q->get()->map(fn (Producto $p) => [
            ...$p->only(self::CAMPOS),
            'medidas' => $p->medidasTexto(),
            'galeria' => $p->galeria(),
        ]));
    }

    /** Valores disponibles para los filtros del catálogo */
    public function filtros(): JsonResponse
    {
        $base = Producto::query()->where('publico', true);

        return response()->json([
            'tipos' => (clone $base)->distinct()->orderBy('tipo')->pluck('tipo'),
            'materiales' => (clone $base)->whereNotNull('material')->distinct()->orderBy('material')->pluck('material'),
            'colores' => (clone $base)->whereNotNull('color')->distinct()->orderBy('color')->pluck('color'),
            'precio_min' => (int) (clone $base)->min('precio'),
            'precio_max' => (int) (clone $base)->max('precio'),
        ]);
    }
}
