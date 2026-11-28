<?php

namespace App\Modules\Donaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Donaciones\Infrastructure\Models\CategoriaOrganizacion;
use App\Shared\Domain\ReglaNegocioException;
use App\Shared\Http\CrudSimple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaOrganizacionController extends Controller
{
    use CrudSimple {
        destroy as private eliminar;
    }

    public function destroy(int $id): JsonResponse
    {
        if (CategoriaOrganizacion::findOrFail($id)->organizaciones()->exists()) {
            throw ReglaNegocioException::conflicto('La categoría tiene organizaciones asociadas. Cámbielas de categoría antes de eliminarla.', 'en_uso');
        }

        return $this->eliminar($id);
    }

    protected function modelo(): string
    {
        return CategoriaOrganizacion::class;
    }

    protected function consulta(Request $request): Builder
    {
        return CategoriaOrganizacion::query()->withCount('organizaciones');
    }

    protected function reglas(Request $request, $registro = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80', Rule::unique('categorias_organizacion', 'nombre')->ignore($registro?->id)->whereNull('deleted_at')],
            'descripcion' => ['nullable', 'string', 'max:250'],
        ];
    }
}
