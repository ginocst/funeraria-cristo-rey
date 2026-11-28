<?php

namespace App\Modules\Donaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Donaciones\Application\ResumenDonaciones;
use App\Modules\Donaciones\Infrastructure\Models\Donacion;
use App\Shared\Http\CrudSimple;
use App\Shared\Infrastructure\HoraLocal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Registro de donaciones */
class DonacionController extends Controller
{
    use CrudSimple;

    public function mensual(Request $request, ResumenDonaciones $resumen): JsonResponse
    {
        $mes = $request->validate(['mes' => ['required', 'date_format:Y-m']])['mes'];

        return response()->json($resumen->mensual($mes));
    }

    protected function modelo(): string
    {
        return Donacion::class;
    }

    protected function buscables(): array
    {
        return ['descripcion'];
    }

    protected function orden(): array
    {
        return ['fecha', 'desc'];
    }

    protected function consulta(Request $request): Builder
    {
        $request->validate(['mes' => ['nullable', 'date_format:Y-m']]);
        $q = Donacion::query()->with('organizacion:id,nombre');
        if ($request->filled('organizacion_id')) {
            $q->where('organizacion_id', $request->integer('organizacion_id'));
        }
        if ($request->filled('mes')) {
            $inicio = CarbonImmutable::createFromFormat('!Y-m', $request->query('mes'));
            $q->where('fecha', '>=', $inicio->toDateString())->where('fecha', '<', $inicio->addMonth()->toDateString());
        }

        return $q;
    }

    protected function reglas(Request $request, $registro = null): array
    {
        $hoy = now(HoraLocal::zona())->toDateString();

        return [
            // Solo organizaciones activas; al editar se puede conservar la que ya tenía
            'organizacion_id' => ['required', 'integer', Rule::exists('organizaciones', 'id')->whereNull('deleted_at')
                ->where(fn($q) => $q->where(fn($w) => $w->where('activo', true)->orWhere('id', $registro?->organizacion_id)))],
            'fecha' => ['required', 'date_format:Y-m-d', "before_or_equal:$hoy"],
            'monto' => ['required', 'integer', 'min:1', 'max:100000000'],
            'descripcion' => ['nullable', 'string', 'max:250'],
        ];
    }
}
