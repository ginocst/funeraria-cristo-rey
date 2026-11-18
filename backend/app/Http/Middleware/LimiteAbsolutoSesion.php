<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierre de sesión a las 8 horas absolutas (la inactividad de 30 minutos la
 * aplica SESSION_LIFETIME). Las cuentas desactivadas pierden la sesión de inmediato.
 */
class LimiteAbsolutoSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        // Sin sesión (una petición que no viene de la SPA, como las pruebas automáticas) no hay hora de inicio que revisar
        $sesion = $request->hasSession() ? $request->session() : null;
        $inicio = (int) $sesion?->get('inicio_sesion_at', 0);
        $vencida = $inicio > 0 && now()->timestamp - $inicio > config('funeraria.sesion_absoluta_minutos') * 60;

        if ($usuario && ($vencida || ! $usuario->activo || $usuario->trashed())) {
            Auth::guard('web')->logout();
            $sesion?->invalidate();
            $sesion?->regenerateToken();

            return response()->json(['message' => 'La sesión expiró. Inicie sesión nuevamente.', 'codigo' => 'sesion_expirada'], 401);
        }

        return $next($request);
    }
}
