<?php

namespace App\Modules\Identidad\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identidad\Infrastructure\Models\Sucursal;
use App\Shared\Http\CrudSimple;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SucursalController extends Controller
{
    use CrudSimple;

    protected function modelo(): string
    {
        return Sucursal::class;
    }

    protected function reglas(Request $request, $registro = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120', Rule::unique('sucursales', 'nombre')->ignore($registro?->id)->whereNull('deleted_at')],
            'direccion' => ['nullable', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:30'],
        ];
    }
}
