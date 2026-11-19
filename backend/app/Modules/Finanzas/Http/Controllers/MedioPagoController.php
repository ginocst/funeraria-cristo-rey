<?php

namespace App\Modules\Finanzas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finanzas\Infrastructure\Models\MedioPago;
use App\Shared\Http\CrudSimple;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedioPagoController extends Controller
{
    use CrudSimple;

    protected function modelo(): string
    {
        return MedioPago::class;
    }

    protected function reglas(Request $request, $registro = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:60', Rule::unique('medios_pago', 'nombre')->ignore($registro?->id)],
            'requiere_referencia' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
