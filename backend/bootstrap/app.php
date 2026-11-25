<?php

use App\Http\Middleware\ExigirMfa;
use App\Http\Middleware\Idempotencia;
use App\Http\Middleware\LimiteAbsolutoSesion;
use App\Shared\Domain\ReglaNegocioException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // La SPA se autentica con la sesión del servidor (Sanctum en modo SPA).
        $middleware->statefulApi();
        // CloudFront y el balanceador de carga están delante de la API.
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'mfa' => ExigirMfa::class,
            'sesion.absoluta' => LimiteAbsolutoSesion::class,
            'idempotencia' => Idempotencia::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Reglas de negocio: mensaje claro para el usuario, sin trazas.
        $exceptions->render(function (ReglaNegocioException $e) {
            return response()->json(['message' => $e->getMessage(), 'codigo' => $e->codigo], $e->estado);
        });

        // Violaciones de restricciones de la base: se traducen a 409/422 con un mensaje, nunca se expone el SQL.
        $exceptions->render(function (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? null;

            return match ($sqlState) {
                '23P01' => response()->json(['message' => 'El vehículo u operario ya está asignado en ese horario.', 'codigo' => 'traslape'], 409),
                '23505' => response()->json(['message' => 'Ya existe un registro con ese dato único.', 'codigo' => 'duplicado'], 409),
                '23514' => response()->json(['message' => 'Los datos no cumplen una regla de integridad.', 'codigo' => 'integridad'], 422),
                '23503' => response()->json(['message' => 'El registro está relacionado con otros datos y no se puede modificar así.', 'codigo' => 'relacionado'], 409),
                'P0001' => response()->json(['message' => 'Este registro no se puede modificar; use un movimiento compensatorio.', 'codigo' => 'inmutable'], 409),
                default => null,
            };
        });
    })->create();
