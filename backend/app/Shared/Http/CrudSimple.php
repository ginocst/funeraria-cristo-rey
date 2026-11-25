<?php

namespace App\Shared\Http;

use App\Shared\Contratos\IAuditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mantenedor genérico (listar, crear, ver, editar y desactivar) para los
 * catálogos simples (sucursales, medios de pago, clientes, convenios, proveedores, vehículos y gastos)
 * Cada controlador define el modelo, las reglas de
 * validación (lista blanca) y los campos de búsqueda. Los permisos se
 * aplican en las rutas con el middleware can:
 */
trait CrudSimple
{
    /** @return class-string<Model> */
    abstract protected function modelo(): string;

    abstract protected function reglas(Request $request, $registro = null): array;

    protected function buscables(): array
    {
        return ['nombre'];
    }

    protected function consulta(Request $request): Builder
    {
        return ($this->modelo())::query();
    }

    protected function orden(): array
    {
        return ['nombre', 'asc'];
    }

    public function index(Request $request): JsonResponse
    {
        $q = $this->consulta($request);
        if ($texto = trim((string) $request->query('q'))) {
            $q->where(function (Builder $w) use ($texto) {
                foreach ($this->buscables() as $campo) {
                    $w->orWhere($campo, 'ilike', '%'.addcslashes($texto, '%_').'%');
                }
            });
        }
        if ($request->filled('activo')) {
            $q->where('activo', $request->boolean('activo'));
        }
        [$campo, $dir] = $this->orden();

        return response()->json($q->orderBy($campo, $dir)->paginate(min((int) $request->query('por_pagina', 25), 200)));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(($this->modelo())::findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas($request));
        $registro = ($this->modelo())::create($this->prepararDatos($datos));
        app(IAuditoria::class)->registrar('crear', $registro->getTable(), $registro->getKey(), null, $registro->toArray());

        return response()->json($registro->fresh(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $registro = ($this->modelo())::findOrFail($id);
        $antes = $registro->toArray();
        $datos = $request->validate($this->reglas($request, $registro));
        $registro->update($this->prepararDatos($datos, $registro));
        app(IAuditoria::class)->registrar('editar', $registro->getTable(), $registro->getKey(), $antes, $registro->fresh()->toArray());

        return response()->json($registro->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $registro = ($this->modelo())::findOrFail($id);
        $registro->delete();
        app(IAuditoria::class)->registrar('eliminar', $registro->getTable(), $registro->getKey(), $registro->toArray());

        return response()->json(null, 204);
    }

    protected function prepararDatos(array $datos, $registro = null): array
    {
        return $datos;
    }
}
