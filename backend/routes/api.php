<?php

/*
|--------------------------------------------------------------------------
| API REST del sistema
|--------------------------------------------------------------------------
| Autenticación: Sanctum SPA (cookie de sesión + XSRF-TOKEN).
|   GET /api/sanctum/csrf-cookie → entrega la cookie XSRF-TOKEN
|   POST /api/auth/login, /api/auth/logout, /api/auth/two-factor-challenge,
|        /api/auth/forgot-password, /api/auth/reset-password, /api/auth/user/two-factor-*
|        (rutas de Laravel Fortify, ver config/fortify.php)
|
| Autorización: cada ruta protegida declara su permiso con can:<permiso>
| (matriz de permisos en config/funeraria.php). Una prueba automática verifica que
| ninguna ruta autenticada quede sin permiso (denegación por defecto).
*/

use App\Modules\Auditoria\Http\Controllers\AuditoriaController;
use App\Modules\ClientesConvenios\Http\Controllers\ClienteController;
use App\Modules\ClientesConvenios\Http\Controllers\ConvenioController;
use App\Modules\Donaciones\Http\Controllers\CategoriaOrganizacionController;
use App\Modules\Donaciones\Http\Controllers\DonacionController;
use App\Modules\Donaciones\Http\Controllers\OrganizacionController;
use App\Modules\Expedientes\Http\Controllers\ActividadController;
use App\Modules\Expedientes\Http\Controllers\DocumentoController;
use App\Modules\Expedientes\Http\Controllers\ExpedienteController;
use App\Modules\Finanzas\Http\Controllers\FinanzasController;
use App\Modules\Finanzas\Http\Controllers\GastoController;
use App\Modules\Finanzas\Http\Controllers\MedioPagoController;
use App\Modules\Flota\Http\Controllers\AusenciaController;
use App\Modules\Flota\Http\Controllers\DiasLibresController;
use App\Modules\Flota\Http\Controllers\DisponibilidadController;
use App\Modules\Flota\Http\Controllers\ItinerarioController;
use App\Modules\Flota\Http\Controllers\VehiculoController;
use App\Modules\Identidad\Http\Controllers\ParametroController;
use App\Modules\Identidad\Http\Controllers\SesionController;
use App\Modules\Identidad\Http\Controllers\SucursalController;
use App\Modules\Identidad\Http\Controllers\UsuarioController;
use App\Modules\Inventario\Http\Controllers\CatalogoPublicoController;
use App\Modules\Inventario\Http\Controllers\ProductoController;
use App\Modules\Inventario\Http\Controllers\ProveedorController;
use App\Modules\Notificaciones\Http\Controllers\AvisoController;
use App\Modules\Reportes\Http\Controllers\DashboardController;
use App\Modules\Reportes\Http\Controllers\DocumentosPdfController;
use App\Modules\Solicitudes\Http\Controllers\CalendarioController;
use App\Modules\Solicitudes\Http\Controllers\SalaVelatoriaController;
use App\Modules\Solicitudes\Http\Controllers\SolicitudController;
use Illuminate\Support\Facades\Route;

/* ---------- Público (catálogo y convenios) ---------- */

Route::prefix('publico')->middleware('throttle:publico')->group(function () {
    Route::get('catalogo', [CatalogoPublicoController::class, 'index']);
    Route::get('catalogo/filtros', [CatalogoPublicoController::class, 'filtros']);
    Route::get('convenios', [ConvenioController::class, 'publicos']);
});

/* ---------- Sesión ---------- */
Route::middleware(['auth:sanctum', 'sesion.absoluta', 'throttle:api'])->group(function () {
    // /me no exige MFA: la interfaz lo usa para saber si debe mostrar la configuración del TOTP
    Route::get('me', [SesionController::class, 'yo']);

    Route::middleware('mfa')->group(function () {
        /* Avisos del propio usuario (no requieren permiso: cada uno ve solo los suyos) */
        Route::get('avisos', [AvisoController::class, 'index']);
        Route::post('avisos/leer-todos', [AvisoController::class, 'leerTodos']);
        Route::post('avisos/{id}/leer', [AvisoController::class, 'leer'])->whereNumber('id');
        Route::get('avisos/stream', [AvisoController::class, 'stream']);

        Route::middleware('can:reportes.operativos')->group(function () {
            Route::get('dashboard', DashboardController::class);
            Route::get('dashboard.pdf', [DocumentosPdfController::class, 'panelPdf']);
            Route::get('dashboard.xlsx', [DocumentosPdfController::class, 'panelExcel']);
        });

        /* ---------- Identidad y configuración ---------- */
        Route::middleware('can:usuarios.gestionar')->group(function () {
            Route::get('usuarios', [UsuarioController::class, 'index']);
            Route::post('usuarios', [UsuarioController::class, 'store']);
            Route::get('usuarios/{id}', [UsuarioController::class, 'show']);
            Route::put('usuarios/{id}', [UsuarioController::class, 'update']);
            Route::delete('usuarios/{id}', [UsuarioController::class, 'destroy']);
            Route::post('usuarios/{id}/suplencia', [UsuarioController::class, 'otorgarSuplencia']);
            Route::delete('usuarios/{id}/suplencia', [UsuarioController::class, 'revocarSuplencia']);
            Route::post('usuarios/{id}/restablecer-mfa', [UsuarioController::class, 'restablecerMfa']);
        });
        Route::get('sucursales', [SucursalController::class, 'index'])->middleware('can:solicitudes.ver');
        Route::middleware('can:configuracion.gestionar')->group(function () {
            Route::post('sucursales', [SucursalController::class, 'store']);
            Route::put('sucursales/{id}', [SucursalController::class, 'update']);
            Route::delete('sucursales/{id}', [SucursalController::class, 'destroy']);
            Route::get('parametros', [ParametroController::class, 'index']);
            Route::put('parametros', [ParametroController::class, 'update']);
            Route::post('medios-pago', [MedioPagoController::class, 'store']);
            Route::put('medios-pago/{id}', [MedioPagoController::class, 'update']);
            Route::delete('medios-pago/{id}', [MedioPagoController::class, 'destroy']);
        });
        Route::get('medios-pago', [MedioPagoController::class, 'index'])->middleware('can:pagos.ver');

        /* ---------- Clientes y convenios ---------- */
        Route::get('clientes', [ClienteController::class, 'index'])->middleware('can:clientes.ver');
        Route::get('clientes/{id}', [ClienteController::class, 'show'])->middleware('can:clientes.ver');
        Route::post('clientes', [ClienteController::class, 'store'])->middleware('can:clientes.crear');
        Route::put('clientes/{id}', [ClienteController::class, 'update'])->middleware('can:clientes.editar');
        Route::delete('clientes/{id}', [ClienteController::class, 'destroy'])->middleware('can:clientes.eliminar');

        Route::get('convenios', [ConvenioController::class, 'index'])->middleware('can:convenios.ver');
        Route::get('convenios/{id}', [ConvenioController::class, 'show'])->middleware('can:convenios.ver');
        Route::middleware('can:convenios.gestionar')->group(function () {
            Route::post('convenios', [ConvenioController::class, 'store']);
            Route::put('convenios/{id}', [ConvenioController::class, 'update']);
            Route::delete('convenios/{id}', [ConvenioController::class, 'destroy']);
        });

        /* ---------- Expedientes de servicio ---------- */
        Route::get('expedientes', [ExpedienteController::class, 'index'])->middleware('can:expedientes.ver');
        Route::get('expedientes/{id}', [ExpedienteController::class, 'show'])->middleware('can:expedientes.ver');
        Route::post('expedientes', [ExpedienteController::class, 'store'])->middleware('can:expedientes.crear');
        Route::put('expedientes/{id}', [ExpedienteController::class, 'update'])->middleware('can:expedientes.editar');
        Route::delete('expedientes/{id}', [ExpedienteController::class, 'destroy'])->middleware('can:expedientes.eliminar');
        Route::middleware('can:expedientes.editar')->group(function () {
            Route::post('expedientes/{expedienteId}/actividades', [ActividadController::class, 'store']);
            Route::put('actividades/{id}', [ActividadController::class, 'update']);
            Route::delete('actividades/{id}', [ActividadController::class, 'destroy']);
            Route::post('expedientes/{expedienteId}/documentos', [DocumentoController::class, 'store']);
            Route::delete('documentos/{id}', [DocumentoController::class, 'destroy']);
        });
        Route::get('documentos/{id}/url', [DocumentoController::class, 'url'])->middleware('can:expedientes.ver');

        /* ---------- Solicitudes de servicio ---------- */
        Route::get('solicitudes', [SolicitudController::class, 'index'])->middleware('can:solicitudes.ver');
        Route::get('solicitudes/{id}', [SolicitudController::class, 'show'])->middleware('can:solicitudes.ver');
        Route::post('solicitudes', [SolicitudController::class, 'store'])->middleware('can:solicitudes.crear');
        Route::put('solicitudes/{id}', [SolicitudController::class, 'update'])->middleware('can:solicitudes.editar');
        Route::post('solicitudes/{id}/cancelar', [SolicitudController::class, 'cancelar'])->middleware('can:solicitudes.editar');
        Route::post('solicitudes/{id}/ejecutar', [SolicitudController::class, 'ejecutar'])->middleware('can:solicitudes.editar');
        Route::middleware('can:solicitudes.evaluar')->group(function () {
            Route::post('solicitudes/{id}/aceptar', [SolicitudController::class, 'aceptar'])->middleware('idempotencia');
            Route::post('solicitudes/{id}/observar', [SolicitudController::class, 'observar']);
            Route::post('solicitudes/{id}/no-aplica', [SolicitudController::class, 'noAplica']);
        });
        Route::get('solicitudes/{id}/orden.pdf', [DocumentosPdfController::class, 'ordenServicio'])->middleware('can:solicitudes.ver');
        Route::get('disponibilidad', DisponibilidadController::class)->middleware('can:solicitudes.crear');
        Route::get('salas-velatorias', [SalaVelatoriaController::class, 'index'])->middleware('can:solicitudes.ver');
        Route::get('salas-velatorias/agenda', [SalaVelatoriaController::class, 'agenda'])->middleware('can:solicitudes.ver');
        Route::middleware('can:salas.gestionar')->group(function () {
            Route::post('salas-velatorias/{id}/estado', [SalaVelatoriaController::class, 'cambiarEstado'])->whereNumber('id');
            Route::post('salas-velatorias', [SalaVelatoriaController::class, 'store']);
            Route::put('salas-velatorias/{id}', [SalaVelatoriaController::class, 'update'])->whereNumber('id');
            Route::delete('salas-velatorias/{id}', [SalaVelatoriaController::class, 'destroy'])->whereNumber('id');
        });
        Route::get('calendario', CalendarioController::class)->middleware('can:calendario.ver');

        /* ---------- Inventario y proveedores ---------- */
        Route::get('productos', [ProductoController::class, 'index'])->middleware('can:productos.ver');
        Route::get('productos/{id}', [ProductoController::class, 'show'])->middleware('can:productos.ver');
        Route::post('productos', [ProductoController::class, 'store'])->middleware('can:productos.crear');
        Route::put('productos/{id}', [ProductoController::class, 'update'])->middleware('can:productos.editar');
        Route::post('productos/{id}/foto', [ProductoController::class, 'foto'])->middleware('can:productos.editar');
        Route::post('productos/{id}/ajuste', [ProductoController::class, 'ajustar'])->middleware('can:productos.editar');
        Route::post('productos/{id}/merma', [ProductoController::class, 'merma'])->middleware('can:productos.editar');
        Route::get('productos/{id}/fotos', [ProductoController::class, 'fotos'])->middleware('can:productos.ver');
        Route::post('productos/{id}/fotos', [ProductoController::class, 'agregarFoto'])->middleware('can:productos.editar');
        Route::delete('productos/{id}/fotos/{fotoId}', [ProductoController::class, 'quitarFoto'])->middleware('can:productos.editar');
        Route::delete('productos/{id}', [ProductoController::class, 'destroy'])->middleware('can:productos.eliminar');
        Route::post('productos/{id}/ingresos', [ProductoController::class, 'ingresar'])->middleware('can:mercaderia.ingresar');
        Route::get('movimientos-stock', [ProductoController::class, 'movimientos'])->middleware('can:productos.ver');

        Route::get('proveedores', [ProveedorController::class, 'index'])->middleware('can:proveedores.ver');
        Route::get('proveedores/{id}', [ProveedorController::class, 'show'])->middleware('can:proveedores.ver');
        Route::middleware('can:proveedores.gestionar')->group(function () {
            Route::post('proveedores', [ProveedorController::class, 'store']);
            Route::put('proveedores/{id}', [ProveedorController::class, 'update']);
            Route::delete('proveedores/{id}', [ProveedorController::class, 'destroy']);
            Route::post('proveedores/{id}/productos', [ProveedorController::class, 'asociarProducto']);
            Route::delete('proveedores/{id}/productos/{productoId}', [ProveedorController::class, 'desasociarProducto']);
        });

        /* ---------- Flota y personal ---------- */
        Route::get('vehiculos', [VehiculoController::class, 'index'])->middleware('can:vehiculos.ver');
        Route::get('vehiculos/agenda', [VehiculoController::class, 'agenda'])->middleware('can:vehiculos.ver');
        Route::get('vehiculos/{id}', [VehiculoController::class, 'show'])->middleware('can:vehiculos.ver')->whereNumber('id');
        Route::post('vehiculos', [VehiculoController::class, 'store'])->middleware('can:vehiculos.crear');
        Route::put('vehiculos/{id}', [VehiculoController::class, 'update'])->middleware('can:vehiculos.editar');
        Route::delete('vehiculos/{id}', [VehiculoController::class, 'destroy'])->middleware('can:vehiculos.eliminar');

        // El operario consulta sus propias ausencias; solo el administrador las gestiona
        Route::get('ausencias', [AusenciaController::class, 'index'])->middleware('can:itinerario.ver');
        Route::middleware('can:ausencias.gestionar')->group(function () {
            Route::post('ausencias', [AusenciaController::class, 'store']);
            Route::put('ausencias/{id}', [AusenciaController::class, 'update']);
            Route::delete('ausencias/{id}', [AusenciaController::class, 'destroy']);
        });
        Route::get('dias-libres', [DiasLibresController::class, 'index'])->middleware('can:dias_libres.ver');
        Route::post('dias-libres', [DiasLibresController::class, 'store'])->middleware('can:dias_libres.solicitar');
        Route::middleware('can:dias_libres.evaluar')->group(function () {
            Route::post('dias-libres/{id}/aprobar', [DiasLibresController::class, 'aprobar'])->whereNumber('id');
            Route::post('dias-libres/{id}/rechazar', [DiasLibresController::class, 'rechazar'])->whereNumber('id');
        });
        Route::get('itinerario', [ItinerarioController::class, 'index'])->middleware('can:itinerario.ver');
        Route::get('itinerario/{id}', [ItinerarioController::class, 'show'])->middleware('can:itinerario.ver');

        /* ---------- Finanzas ---------- */
        Route::get('expedientes/{expedienteId}/finanzas', [FinanzasController::class, 'resumen'])->middleware('can:pagos.ver');
        Route::post('expedientes/{expedienteId}/cuotas', [FinanzasController::class, 'pactarCuotas'])->middleware('can:pagos.registrar');
        Route::get('pagos', [FinanzasController::class, 'pagos'])->middleware('can:pagos.ver');
        Route::post('pagos', [FinanzasController::class, 'registrar'])->middleware(['can:pagos.registrar', 'idempotencia']);
        Route::post('pagos/{id}/anular', [FinanzasController::class, 'anular'])->middleware('can:pagos.anular');
        Route::get('comprobantes/{id}/pdf', [DocumentosPdfController::class, 'comprobante'])->middleware('can:pagos.ver');
        Route::get('cobranza', [FinanzasController::class, 'cobranza'])->middleware('can:pagos.ver');

        Route::middleware('can:gastos.gestionar')->group(function () {
            Route::get('gastos', [GastoController::class, 'index']);
            Route::get('gastos/categorias', [GastoController::class, 'categorias']);
            Route::post('gastos', [GastoController::class, 'store']);
            Route::put('gastos/{id}', [GastoController::class, 'update']);
            Route::delete('gastos/{id}', [GastoController::class, 'destroy']);
        });

        /* ---------- Donaciones ---------- */
        Route::middleware('can:donaciones.gestionar')->group(function () {
            foreach (
                [
                    'categorias-organizacion' => CategoriaOrganizacionController::class,
                    'organizaciones' => OrganizacionController::class,
                    'donaciones' => DonacionController::class,
                ] as $recurso => $controlador
            ) {
                Route::get($recurso, [$controlador, 'index']);
                Route::post($recurso, [$controlador, 'store']);
                Route::put("$recurso/{id}", [$controlador, 'update'])->whereNumber('id');
                Route::delete("$recurso/{id}", [$controlador, 'destroy'])->whereNumber('id');
            }
            Route::get('donaciones/mensual', [DonacionController::class, 'mensual']);
            Route::get('donaciones/mensual.pdf', [DocumentosPdfController::class, 'donacionesPdf']);
            Route::get('donaciones/mensual.xlsx', [DocumentosPdfController::class, 'donacionesExcel']);
            Route::get('organizaciones/{id}/donaciones.pdf', [DocumentosPdfController::class, 'donacionesOrganizacionPdf'])->whereNumber('id');
        });

        /* ---------- Reportes y bitácora ---------- */
        Route::middleware('can:reportes.financieros')->group(function () {
            Route::get('reportes/mensual', [DocumentosPdfController::class, 'mensualJson']);
            Route::get('reportes/mensual.pdf', [DocumentosPdfController::class, 'mensualPdf']);
            Route::get('reportes/mensual.xlsx', [DocumentosPdfController::class, 'mensualExcel']);
        });
        Route::get('auditorias', [AuditoriaController::class, 'index'])->middleware('can:bitacora.ver');
    });
});
