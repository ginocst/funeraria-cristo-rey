<?php

namespace App\Modules\ClientesConvenios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ClientesConvenios\Infrastructure\Models\Convenio;
use App\Shared\Http\CrudSimple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConvenioController extends Controller
{
    use CrudSimple;

    protected function modelo(): string
    {
        return Convenio::class;
    }

    protected function buscables(): array
    {
        return ['nombre', 'institucion'];
    }

    protected function consulta(Request $request): Builder
    {
        $q = Convenio::query()->withCount('clientes');
        if ($request->query('estado') === 'vigentes') {
            $hoy = now(config('funeraria.zona_horaria_local'))->toDateString();
            $q->where('activo', true)->where('vigencia_desde', '<=', $hoy)
                ->where(fn ($w) => $w->whereNull('vigencia_hasta')->orWhere('vigencia_hasta', '>=', $hoy));
        }
        if ($request->filled('tipo')) {
            $q->where('tipo', $request->query('tipo'));
        }

        return $q;
    }

    protected function reglas(Request $request, $registro = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'institucion' => ['required', 'string', 'max:160'],
            'tipo' => ['required', Rule::in(['descuento', 'cuota_mortuoria', 'institucional'])],
            'descuento_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'vigencia_desde' => ['required', 'date'],
            'vigencia_hasta' => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'contacto' => ['nullable', 'string', 'max:160'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /** Lista pública (sin sesión) de convenios vigentes, para el pie del catálogo */
    public function publicos(): JsonResponse
    {
        $hoy = now(config('funeraria.zona_horaria_local'))->toDateString();

        return response()->json(Convenio::where('activo', true)->where('vigencia_desde', '<=', $hoy)
            ->where(fn ($w) => $w->whereNull('vigencia_hasta')->orWhere('vigencia_hasta', '>=', $hoy))
            ->orderBy('nombre')->get(['id', 'nombre', 'institucion', 'tipo'])
            ->map(fn ($c) => $c->only(['id', 'nombre', 'institucion', 'tipo'])));
    }
}
