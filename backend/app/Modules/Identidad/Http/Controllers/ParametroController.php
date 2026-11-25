<?php

namespace App\Modules\Identidad\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identidad\Infrastructure\Models\Parametro;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Infrastructure\Parametros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Parámetros configurables del negocio (anticipación, abono mínimo, umbrales…) */
class ParametroController extends Controller
{
    public function index(Parametros $parametros): JsonResponse
    {
        $valores = $parametros->todos();

        return response()->json(collect(config('funeraria.parametros'))->map(fn ($p, $clave) => [
            'clave' => $clave,
            'valor' => (int) $valores[$clave],
            'descripcion' => $p['descripcion'],
        ])->values());
    }

    public function update(Request $request, Parametros $parametros, IAuditoria $auditoria): JsonResponse
    {
        $claves = array_keys(config('funeraria.parametros'));
        $reglas = collect($claves)->mapWithKeys(fn ($c) => [$c => ['sometimes', 'integer', 'min:0', 'max:10000']])->all();
        $reglas['abono_minimo_pct'] = ['sometimes', 'integer', 'min:0', 'max:100'];
        $datos = $request->validate($reglas);
        $antes = $parametros->todos();
        foreach ($datos as $clave => $valor) {
            Parametro::updateOrCreate(['clave' => $clave], ['valor' => (string) $valor, 'descripcion' => config("funeraria.parametros.$clave.descripcion")]);
        }
        $parametros->olvidar();
        $auditoria->registrar('editar_parametros', 'parametros', null, array_intersect_key($antes, $datos), $datos);

        return $this->index($parametros);
    }
}
