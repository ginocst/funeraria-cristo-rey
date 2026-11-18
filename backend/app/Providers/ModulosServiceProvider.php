<?php

namespace App\Providers;

use App\Modules\Auditoria\Application\EventosDeAcceso;
use App\Modules\Auditoria\Infrastructure\Auditor;
use App\Modules\Expedientes\Infrastructure\ServicioExpediente;
use App\Modules\Flota\Infrastructure\ServicioDisponibilidad;
use App\Modules\Inventario\Infrastructure\ServicioStock;
use App\Modules\Notificaciones\Infrastructure\PublicadorOutbox;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Contratos\IDisponibilidad;
use App\Shared\Contratos\IEventos;
use App\Shared\Contratos\IExpediente;
use App\Shared\Contratos\IStock;
use App\Shared\Infrastructure\Parametros;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Conecta las interfaces entre módulos con su implementación
 * Cambiar un adaptador es cambiar una línea aquí
 */
class ModulosServiceProvider extends ServiceProvider
{
    public array $singletons = [
        IStock::class => ServicioStock::class,           // Inventario
        IDisponibilidad::class => ServicioDisponibilidad::class, // Flota y personal
        IExpediente::class => ServicioExpediente::class, // Expedientes de servicio
        IEventos::class => PublicadorOutbox::class,      // Notificaciones (outbox)
        IAuditoria::class => Auditor::class,             // Auditoría
        Parametros::class => Parametros::class,
    ];

    public function boot(): void
    {
        Event::subscribe(EventosDeAcceso::class);
    }
}
