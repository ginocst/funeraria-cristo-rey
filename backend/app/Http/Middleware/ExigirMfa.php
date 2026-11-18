<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** MFA obligatorio para Administrador y Coordinador: sin TOTP confirmado no se opera */
class ExigirMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        if ($usuario && $usuario->requiereMfa() && ! $usuario->mfaActivo()) {
            return response()->json([
                'message' => 'Debe configurar la verificación en dos pasos antes de continuar.',
                'codigo' => 'mfa_requerido',
            ], 403);
        }

        return $next($request);
    }
}
