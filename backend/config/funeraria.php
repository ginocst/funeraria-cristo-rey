<?php

/*
| Configuración del negocio. Cada permiso lista los roles que lo tienen.
| Lo que no aparece aquí queda denegado (denegación por defecto).
*/

return [
    'zona_horaria_local' => 'America/Santiago',

    // Dirección de la interfaz (enlaces de recuperación de contraseña y avisos).
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // Datos de demostración en "php artisan db:seed" (sólo en desarrollo).
    'seed_demo' => (bool) env('SEED_DEMO', true),

    // TOTP obligatorio para Administrador y Coordinador (opcional para Operario).
    'mfa_obligatorio' => (bool) env('MFA_OBLIGATORIO', true),
    'roles_con_mfa' => ['Administrador', 'Coordinador'],

    // Cierre de sesión a las 8 horas (la inactividad la controla SESSION_LIFETIME=30).
    'sesion_absoluta_minutos' => (int) env('SESION_ABSOLUTA_MINUTOS', 480),

    // Vigencia de las URL prefirmadas para archivos privados.
    'url_prefirmada_minutos' => 5,

    // Valores iniciales de los parámetros configurables.
    'parametros' => [
        'anticipacion_minima_horas' => ['valor' => '24', 'descripcion' => 'Horas mínimas entre el registro de la solicitud y el traslado'],
        'abono_minimo_pct' => ['valor' => '50', 'descripcion' => 'Porcentaje del total que debe estar pagado para aceptar una solicitud'],
        'duracion_servicio_horas' => ['valor' => '48', 'descripcion' => 'Duración máxima entre el inicio del velatorio y el traslado'],
        'duracion_traslado_horas' => ['valor' => '3', 'descripcion' => 'Tiempo que el vehículo y el equipo quedan comprometidos desde el traslado'],
        'presentacion_previa_minutos' => ['valor' => '60', 'descripcion' => 'Minutos antes del traslado en que el equipo debe presentarse'],
        'umbral_stock' => ['valor' => '3', 'descripcion' => 'Stock mínimo por defecto para nuevos productos'],
        'umbral_stock_infantil' => ['valor' => '2', 'descripcion' => 'Stock mínimo por defecto para urnas infantiles'],
    ],

    'permisos' => [
        'usuarios.gestionar' => ['Administrador'],
        'configuracion.gestionar' => ['Administrador'],

        'clientes.ver' => ['Administrador', 'Coordinador'],
        'clientes.crear' => ['Administrador', 'Coordinador'],
        'clientes.editar' => ['Administrador', 'Coordinador'],
        'clientes.eliminar' => ['Administrador'],

        'convenios.ver' => ['Administrador', 'Coordinador'],
        'convenios.gestionar' => ['Administrador'],

        'expedientes.ver' => ['Administrador', 'Coordinador'],
        'expedientes.crear' => ['Administrador', 'Coordinador'],
        'expedientes.editar' => ['Administrador', 'Coordinador'],
        'expedientes.eliminar' => ['Administrador'],

        'solicitudes.ver' => ['Administrador', 'Coordinador'],
        'solicitudes.crear' => ['Administrador', 'Coordinador'],
        'solicitudes.editar' => ['Administrador', 'Coordinador'],
        'solicitudes.eliminar' => ['Administrador'],
        'solicitudes.evaluar' => ['Administrador'],

        'salas.gestionar' => ['Administrador'],

        'productos.ver' => ['Administrador', 'Coordinador', 'Operario'],
        'productos.crear' => ['Administrador', 'Coordinador'],
        'productos.editar' => ['Administrador', 'Coordinador'],
        'productos.eliminar' => ['Administrador'],
        'mercaderia.ingresar' => ['Administrador', 'Coordinador', 'Operario'],

        'proveedores.ver' => ['Administrador', 'Coordinador'],
        'proveedores.gestionar' => ['Administrador'],

        'vehiculos.ver' => ['Administrador', 'Coordinador'],
        'vehiculos.crear' => ['Administrador'],
        'vehiculos.editar' => ['Administrador'],
        'vehiculos.eliminar' => ['Administrador'],

        'ausencias.ver' => ['Administrador', 'Coordinador'],
        'ausencias.gestionar' => ['Administrador'],
        'itinerario.ver' => ['Operario', 'Coordinador', 'Administrador'],

        'dias_libres.ver' => ['Operario', 'Coordinador', 'Administrador'],
        'dias_libres.solicitar' => ['Operario', 'Coordinador'],
        'dias_libres.evaluar' => ['Administrador'],

        'pagos.ver' => ['Administrador', 'Coordinador'],
        'pagos.registrar' => ['Administrador', 'Coordinador'],
        'pagos.anular' => ['Administrador'],

        'calendario.ver' => ['Administrador', 'Coordinador'],
        'gastos.gestionar' => ['Administrador'],
        'donaciones.gestionar' => ['Administrador'],
        'reportes.financieros' => ['Administrador'],
        'reportes.operativos' => ['Administrador', 'Coordinador'],
        'bitacora.ver' => ['Administrador'],
    ],

    // Permisos que la suplencia NO entrega al coordinador.
    'excluidos_de_suplencia' => ['usuarios.gestionar'],
];
