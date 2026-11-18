<?php

namespace App\Providers;

use App\Modules\Identidad\Infrastructure\Models\Usuario;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Asignación masiva solo con $fillable explícito; en desarrollo se detectan accesos perezosos (N+1).
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Matriz de permisos: un gate por permiso. Lo no definido queda denegado.
        foreach (array_keys(config('funeraria.permisos', [])) as $permiso) {
            Gate::define($permiso, fn (Usuario $usuario) => $usuario->activo && $usuario->tienePermiso($permiso));
        }

        // Limitación de tasa de la API (además del límite por IP de AWS WAF).

        // 120 peticiones por minuto por usuario. Si la petición no tiene usuario, se cuenta por IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        // 60 por minuto por IP, para el catálogo que ve cualquier persona.
        RateLimiter::for('publico', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // El enlace de recuperación apunta a la interfaz (SPA).
        ResetPassword::createUrlUsing(fn ($usuario, string $token) => rtrim(config('funeraria.frontend_url'), '/')
            .'/restablecer?token='.$token.'&email='.urlencode($usuario->email));
    }
}