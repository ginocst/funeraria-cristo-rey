<?php

namespace App\Modules\Donaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Donaciones\Infrastructure\Models\Organizacion;
use App\Shared\Domain\ReglaNegocioException;
use App\Shared\Http\CrudSimple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Organizaciones que reciben donaciones, cada una con su historial */
class OrganizacionController extends Controller
{
    use CrudSimple {
        destroy as private eliminar;
    }

    /** El historial se conserva: una organización con donaciones solo se puede desactivar */
    public function destroy(int $id): JsonResponse
    {
        if (Organizacion::findOrFail($id)->donaciones()->exists()) {
            throw ReglaNegocioException::conflicto('La organización tiene donaciones registradas. Desactívela para que no reciba nuevas.', 'en_uso');
        }

        return $this->eliminar($id);
    }

    protected function modelo(): string
    {
        return Organizacion::class;
    }

    protected function consulta(Request $request): Builder
    {
        $q = Organizacion::query()->with('categoria:id,nombre')->withCount('donaciones')->withSum('donaciones as total_donado', 'monto');
        if ($request->filled('categoria_id')) {
            $q->where('categoria_id', $request->integer('categoria_id'));
        }

        return $q;
    }

    protected function reglas(Request $request, $registro = null): array
    {
        return [
            'categoria_id' => ['required', 'integer', Rule::exists('categorias_organizacion', 'id')->whereNull('deleted_at')],
            'nombre' => ['required', 'string', 'max:120', Rule::unique('organizaciones', 'nombre')->ignore($registro?->id)->whereNull('deleted_at')],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'notas' => ['nullable', 'string', 'max:250'],
            'activo' => ['boolean'],
        ];
    }
}
