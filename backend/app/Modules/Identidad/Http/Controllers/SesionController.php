<?php

namespace App\Modules\Identidad\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SesionController extends Controller
{
    /** Perfil del usuario autenticado: roles vigentes, permisos y estado de MFA */
    public function yo(Request $request): JsonResponse
    {
        $u = $request->user()->load('sucursal:id,nombre');

        return response()->json([
            'id' => $u->id,
            'nombre' => $u->nombre,
            'email' => $u->email,
            'sucursal' => $u->sucursal,
            'rol' => $u->rolPrincipal(),
            'roles' => collect($u->rolesVigentes())->pluck('nombre')->unique()->values(),
            'suplencia_hasta' => $u->suplenciaHasta(),
            'permisos' => $u->permisos(),
            'mfa_activo' => $u->mfaActivo(),
            'mfa_requerido' => $u->requiereMfa(),
        ]);
    }
}
