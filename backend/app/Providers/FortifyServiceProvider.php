<?php

namespace App\Providers;

use App\Modules\Identidad\Infrastructure\Models\Usuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Solo cuentas activas y no eliminadas pueden autenticarse
        Fortify::authenticateUsing(function (Request $request) {
            $usuario = Usuario::whereRaw('lower(email) = ?', [Str::lower((string) $request->input('email'))])->first();

            return $usuario && $usuario->activo && Hash::check((string) $request->input('password'), $usuario->password) ? $usuario : null;
        });

        // Límite progresivo desde el quinto intento: 5 por minuto y 20 por hora por cuenta e IP
        RateLimiter::for('login', function (Request $request) {
            $clave = Str::transliterate(Str::lower((string) $request->input(Fortify::username())) . '|' . $request->ip());

            return [Limit::perMinute(5)->by($clave), Limit::perHour(20)->by($clave)];
        });

        RateLimiter::for('two-factor', fn(Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
