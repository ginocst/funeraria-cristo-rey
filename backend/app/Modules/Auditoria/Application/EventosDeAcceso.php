<?php

namespace App\Modules\Auditoria\Application;

use App\Shared\Contratos\IAuditoria;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/** Registra en la bitácora los inicios de sesión, cierres, fallos y bloqueos */
class EventosDeAcceso
{
    public function __construct(private IAuditoria $auditoria) {}

    public function login(Login $e): void
    {
        session()->put('inicio_sesion_at', now()->timestamp);
        $e->user->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        $this->auditoria->registrar('inicio_sesion', 'usuarios', $e->user->getAuthIdentifier());
    }

    public function logout(Logout $e): void
    {
        if ($e->user) {
            $this->auditoria->registrar('cierre_sesion', 'usuarios', $e->user->getAuthIdentifier());
        }
    }

    public function fallido(Failed $e): void
    {
        $this->auditoria->registrar('inicio_sesion_fallido', 'usuarios', $e->user?->getAuthIdentifier(), null, ['email' => $e->credentials['email'] ?? null]);
    }

    public function bloqueo(Lockout $e): void
    {
        $this->auditoria->registrar('bloqueo_por_intentos', 'usuarios', null, null, ['email' => $e->request->input('email')]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Logout::class => 'logout',
            Failed::class => 'fallido',
            Lockout::class => 'bloqueo',
        ];
    }
}
