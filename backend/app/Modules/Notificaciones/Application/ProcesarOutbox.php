<?php

namespace App\Modules\Notificaciones\Application;

use App\Modules\Identidad\Infrastructure\Models\Usuario;
use App\Modules\Notificaciones\Infrastructure\Models\Aviso;
use App\Modules\Notificaciones\Infrastructure\Models\Notificacion;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Trabajador asíncrono: toma los eventos pendientes del outbox (FOR UPDATE
 * SKIP LOCKED, para poder correr más de un trabajador) y crea los avisos de
 * cada destinatario. Un evento que falla se reintenta hasta cinco veces
 */
class ProcesarOutbox
{
    public function lote(int $limite = 50): int
    {
        return DB::transaction(function () use ($limite) {
            $eventos = Notificacion::where('estado', 'pendiente')->orderBy('id')->limit($limite)->lock('FOR UPDATE SKIP LOCKED')->get();
            foreach ($eventos as $evento) {
                try {
                    DB::transaction(fn() => $this->procesar($evento));
                    $evento->update(['estado' => 'procesado', 'procesado_at' => now(), 'error' => null]);
                } catch (Throwable $e) {
                    $reintentos = $evento->reintentos + 1;
                    $evento->update(['reintentos' => $reintentos, 'estado' => $reintentos >= 5 ? 'fallido' : 'pendiente', 'error' => mb_substr($e->getMessage(), 0, 500)]);
                    report($e);
                }
            }

            return $eventos->count();
        });
    }

    private function procesar(Notificacion $evento): void
    {
        $p = $evento->payload;
        $cod = $p['codigo'] ?? '';
        $enlaceSol = isset($p['solicitud_id']) ? '/solicitudes/' . $p['solicitud_id'] : null;
        $enlacePagos = isset($p['expediente_id']) ? '/expedientes/' . $p['expediente_id'] . '?pestana=pagos' : '/cobranza';

        [$destinatarios, $tipo, $titulo, $mensaje, $enlace] = match ($evento->evento) {
            'solicitud.creada' => [$this->conRol('Administrador'), 'info', 'Nueva solicitud en revisión', "La solicitud del expediente $cod espera evaluación.", $enlaceSol],
            'solicitud.aceptada' => [$this->conRol('Coordinador')->merge($this->usuarios($p['equipo'] ?? [])), 'exito', 'Solicitud aceptada', "Se aceptó la solicitud del expediente $cod y se reservaron los recursos.", $enlaceSol],
            'solicitud.observada' => [$this->conRol('Coordinador'), 'alerta', 'Solicitud con observaciones', "El expediente $cod tiene observaciones: " . ($p['comentario'] ?? ''), $enlaceSol],
            'solicitud.no_aplica' => [$this->conRol('Coordinador'), 'alerta', 'Solicitud marcada «no aplica»', "El expediente $cod se cerró sin ejecución.", $enlaceSol],
            'solicitud.cancelada' => [$this->conRol('Administrador')->merge($this->usuarios($p['equipo'] ?? [])), 'alerta', 'Solicitud cancelada', "Se canceló la solicitud del expediente $cod y se liberaron los recursos.", $enlaceSol],
            'solicitud.modificada' => [$this->conRol('Administrador')->merge($this->usuarios($p['equipo'] ?? [])), 'info', 'Solicitud modificada', "Cambió la solicitud del expediente $cod.", $enlaceSol],
            'stock.critico' => [$this->conRol('Administrador', 'Coordinador'), 'critico', 'Stock crítico', ($p['producto'] ?? 'Un producto') . ' quedó con ' . ($p['stock'] ?? 0) . ' unidades.', '/inventario'],
            'pago.anulado' => [$this->conRol('Administrador'), 'alerta', 'Pago anulado', 'Se anuló un pago del expediente ' . $cod . '. Motivo: ' . ($p['motivo'] ?? ''), $enlacePagos],
            'cuota.atrasada' => [$this->conRol('Administrador', 'Coordinador'), 'critico', 'Cuota atrasada', "La cuota {$p['numero']} del expediente $cod venció sin pago.", $enlacePagos],
            default => [collect(), 'info', '', '', null],
        };

        foreach ($destinatarios->unique('id') as $u) {
            // Quien no puede ver solicitudes (el equipo de operarios) va a su itinerario
            $destino = $enlace !== null && str_starts_with($enlace, '/solicitudes/') && ! $u->tienePermiso('solicitudes.ver') ? '/itinerario' : $enlace;
            Aviso::create(['usuario_id' => $u->id, 'notificacion_id' => $evento->id, 'tipo' => $tipo, 'titulo' => $titulo, 'mensaje' => mb_substr($mensaje, 0, 400), 'enlace' => $destino]);
        }
    }

    private function conRol(string ...$roles)
    {
        return Usuario::where('activo', true)->get()->filter(fn(Usuario $u) => $u->tieneRol(...$roles))->values();
    }

    private function usuarios(array $ids)
    {
        return Usuario::whereIn('id', $ids)->where('activo', true)->get();
    }
}
