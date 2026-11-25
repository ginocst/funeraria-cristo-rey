<?php

namespace App\Modules\Solicitudes\Application;

use App\Modules\Expedientes\Infrastructure\Models\Expediente;
use App\Modules\Flota\Infrastructure\Models\Asignacion;
use App\Modules\Flota\Infrastructure\Models\Vehiculo;
use App\Modules\Inventario\Infrastructure\Models\Producto;
use App\Modules\Solicitudes\Domain\EstadoSolicitud;
use App\Modules\Solicitudes\Domain\PoliticaReserva;
use App\Modules\Solicitudes\Infrastructure\Models\Solicitud;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Contratos\IDisponibilidad;
use App\Shared\Contratos\IEventos;
use App\Shared\Contratos\IExpediente;
use App\Shared\Contratos\IStock;
use App\Shared\Domain\ReglaNegocioException;
use App\Shared\Infrastructure\Parametros;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Casos de uso de la solicitud de reserva. Coordina los pasos y la transacción;
 * las reglas viven en el dominio y los otros módulos
 * se usan mediante sus interfaces (IStock, IDisponibilidad, IExpediente, IEventos).
 */
class GestionSolicitudes
{
    public function __construct(
        private IStock $stock,
        private IDisponibilidad $disponibilidad,
        private IExpediente $expedientes,
        private IEventos $eventos,
        private IAuditoria $auditoria,
        private Parametros $parametros,
    ) {}

    /** Crear la solicitud. Queda «en revisión»; los recursos aún no se reservan */
    public function crear(array $datos): Solicitud
    {
        $this->validarReglas($datos);

        return DB::transaction(function () use ($datos) {
            $expediente = Expediente::findOrFail($datos['expediente_id']);
            if (in_array($expediente->estado, ['cerrado', 'anulado'], true)) {
                throw new ReglaNegocioException('El expediente está cerrado o anulado.');
            }
            $solicitud = Solicitud::create([...$this->camposSolicitud($datos), 'expediente_id' => $expediente->id, 'estado' => EstadoSolicitud::EnRevision]);
            $this->guardarProductos($solicitud, $datos['productos']);
            $this->guardarAsignacion($solicitud, $datos);
            $this->auditoria->registrar('crear', 'solicitudes', $solicitud->id, null, $solicitud->toArray());
            $this->eventos->publicar('solicitud.creada', ['solicitud_id' => $solicitud->id, 'codigo' => $expediente->codigo]);

            return $solicitud;
        });
    }

    /**
     * Modificar (por ejemplo, cambio de urna u hora). Si estaba aceptada se
     * modifica sobre sí misma y vuelve a validar los recursos en la misma
     * transacción; si estaba con observaciones, vuelve a «en revisión»
     */
    public function modificar(int $id, array $datos): Solicitud
    {
        $this->validarReglas($datos);

        return DB::transaction(function () use ($id, $datos) {
            $solicitud = Solicitud::with('productos')->whereKey($id)->lockForUpdate()->firstOrFail();
            $estado = $solicitud->estado;
            if ($estado->esTerminal()) {
                throw ReglaNegocioException::conflicto('La solicitud está en un estado terminal y ya no admite cambios.', 'terminal');
            }
            $antes = $solicitud->toArray();
            $equipoAnterior = $this->equipoIds($solicitud->id);

            if ($estado === EstadoSolicitud::Aceptada) {
                $this->stock->liberar($solicitud->id, $solicitud->itemsProductos(), 'Reposición por modificación de la solicitud');
                $this->disponibilidad->liberarAsignacion($solicitud->id);
            }

            $nuevoEstado = $estado === EstadoSolicitud::ConObservaciones ? EstadoSolicitud::EnRevision : $estado;
            $solicitud->update([...$this->camposSolicitud($datos), 'estado' => $nuevoEstado]);
            $this->guardarProductos($solicitud, $datos['productos']);
            $this->guardarAsignacion($solicitud, $datos);

            if ($estado === EstadoSolicitud::Aceptada) {
                $solicitud->load('productos');
                $this->stock->reservar($solicitud->id, $solicitud->itemsProductos());
                $this->disponibilidad->activarAsignacion($solicitud->id);
            }

            $this->auditoria->registrar('modificar', 'solicitudes', $solicitud->id, $antes, $solicitud->fresh()->toArray());
            $this->eventos->publicar('solicitud.modificada', [
                'solicitud_id' => $solicitud->id,
                'codigo' => $solicitud->expediente->codigo,
                'equipo' => array_values(array_unique([...$equipoAnterior, ...$this->equipoIds($solicitud->id)])),
            ]);

            return $solicitud;
        });
    }

    /**
     * Aceptar. En una sola transacción: abono mínimo, bloqueo y
     * descuento de stock, ausencias, activación de la asignación (la exclusión
     * de PostgreSQL rechaza traslapes), auditoría y evento en el outbox.
     * Si cualquier paso falla, se revierte todo y la solicitud sigue en revisión
     */
    public function aceptar(int $id): Solicitud
    {
        return DB::transaction(function () use ($id) {
            $solicitud = Solicitud::with('productos', 'expediente')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->exigirTransicion($solicitud, EstadoSolicitud::Aceptada, [EstadoSolicitud::EnRevision]);

            $resumen = $this->expedientes->resumenFinanciero($solicitud->expediente_id);
            $porcentaje = $this->parametros->entero('abono_minimo_pct');
            if (! PoliticaReserva::abonoMinimoCumplido($resumen['total'], $resumen['pagado'], $porcentaje)) {
                $minimo = PoliticaReserva::abonoMinimoRequerido($resumen['total'], $porcentaje);
                throw new ReglaNegocioException('Falta el abono mínimo: se requieren $'.number_format($minimo, 0, ',', '.')
                    .' ('.$porcentaje.' %) y hay $'.number_format($resumen['pagado'], 0, ',', '.').' pagados.', 'abono_minimo');
            }

            $this->stock->reservar($solicitud->id, $solicitud->itemsProductos());
            $this->disponibilidad->activarAsignacion($solicitud->id);

            $solicitud->update(['estado' => EstadoSolicitud::Aceptada, 'evaluada_por' => Auth::id(), 'evaluada_at' => now()]);
            $solicitud->expediente->update(['estado' => 'en_servicio']);
            $this->auditoria->registrar('aceptar', 'solicitudes', $solicitud->id, ['estado' => 'en_revision'], ['estado' => 'aceptada']);
            $this->eventos->publicar('solicitud.aceptada', ['solicitud_id' => $solicitud->id, 'codigo' => $solicitud->expediente->codigo, 'equipo' => $this->equipoIds($solicitud->id)]);

            return $solicitud;
        });
    }

    /** Observar o marcar «no aplica»: exige un comentario para la familia */
    public function evaluar(int $id, EstadoSolicitud $nuevo, string $comentario): Solicitud
    {
        return DB::transaction(function () use ($id, $nuevo, $comentario) {
            $solicitud = Solicitud::with('expediente')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->exigirTransicion($solicitud, $nuevo, [EstadoSolicitud::EnRevision]);
            $antes = $solicitud->estado->value;
            $solicitud->update(['estado' => $nuevo, 'comentario' => $comentario, 'evaluada_por' => Auth::id(), 'evaluada_at' => now()]);
            $this->auditoria->registrar($nuevo === EstadoSolicitud::NoAplica ? 'no_aplica' : 'observar', 'solicitudes', $id, ['estado' => $antes], ['estado' => $nuevo->value], $comentario);
            $this->eventos->publicar(
                $nuevo === EstadoSolicitud::NoAplica ? 'solicitud.no_aplica' : 'solicitud.observada',
                ['solicitud_id' => $id, 'codigo' => $solicitud->expediente->codigo, 'comentario' => $comentario]
            );

            return $solicitud;
        });
    }

    /** Cancelar. Si estaba aceptada, repone el stock y libera vehículo y equipo */
    public function cancelar(int $id, string $motivo): Solicitud
    {
        return DB::transaction(function () use ($id, $motivo) {
            $solicitud = Solicitud::with('productos', 'expediente')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->exigirTransicion($solicitud, EstadoSolicitud::Cancelada, [EstadoSolicitud::EnRevision, EstadoSolicitud::ConObservaciones, EstadoSolicitud::Aceptada]);
            $estabaAceptada = $solicitud->estado === EstadoSolicitud::Aceptada;
            $equipo = $this->equipoIds($id);
            if ($estabaAceptada) {
                $this->stock->liberar($id, $solicitud->itemsProductos(), 'Reposición por cancelación de la solicitud');
                $this->disponibilidad->liberarAsignacion($id);
            }
            $solicitud->update(['estado' => EstadoSolicitud::Cancelada, 'motivo_cancelacion' => $motivo]);
            $this->auditoria->registrar('cancelar', 'solicitudes', $id, null, ['estado' => 'cancelada', 'liberados' => $estabaAceptada], $motivo);
            $this->eventos->publicar('solicitud.cancelada', ['solicitud_id' => $id, 'codigo' => $solicitud->expediente->codigo, 'equipo' => $estabaAceptada ? $equipo : []]);

            return $solicitud;
        });
    }

    public function ejecutar(int $id): Solicitud
    {
        return DB::transaction(function () use ($id) {
            $solicitud = Solicitud::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->exigirTransicion($solicitud, EstadoSolicitud::Ejecutada, [EstadoSolicitud::Aceptada]);
            $solicitud->update(['estado' => EstadoSolicitud::Ejecutada]);
            $this->auditoria->registrar('ejecutar', 'solicitudes', $id, ['estado' => 'aceptada'], ['estado' => 'ejecutada']);

            return $solicitud;
        });
    }

    private function exigirTransicion(Solicitud $solicitud, EstadoSolicitud $nuevo, array $desde): void
    {
        if (! in_array($solicitud->estado, $desde, true) || ! $solicitud->estado->puedeTransicionarA($nuevo)) {
            throw ReglaNegocioException::conflicto("No se puede pasar de «{$solicitud->estado->etiqueta()}» a «{$nuevo->etiqueta()}».", 'transicion');
        }
    }

    private function validarReglas(array $datos): void
    {
        $traslado = CarbonImmutable::parse($datos['traslado_at'])->utc();
        $velatorio = CarbonImmutable::parse($datos['velatorio_inicio'])->utc();
        PoliticaReserva::validarAnticipacion($traslado, CarbonImmutable::now('UTC'), $this->parametros->entero('anticipacion_minima_horas'));
        PoliticaReserva::validarDuracion($velatorio, $traslado, $this->parametros->entero('duracion_servicio_horas'));
        PoliticaReserva::validarEquipo(count($datos['equipo'] ?? []));
    }

    private function camposSolicitud(array $d): array
    {
        return [
            'sucursal_id' => $d['sucursal_id'],
            'valor_servicio' => $d['valor_servicio'],
            'descuento' => $d['descuento'] ?? 0,
            'velatorio_inicio' => CarbonImmutable::parse($d['velatorio_inicio'])->utc(),
            'lugar_velatorio' => $d['lugar_velatorio'],
            'traslado_at' => CarbonImmutable::parse($d['traslado_at'])->utc(),
            'lugar_sepultura' => $d['lugar_sepultura'],
            'sepultura_detalle' => $d['sepultura_detalle'] ?? null,
            'mensaje' => $d['mensaje'] ?? null,
        ];
    }

    /** Guarda las urnas y elementos con el precio vigente (instantánea del precio) */
    private function guardarProductos(Solicitud $solicitud, array $productos): void
    {
        DB::table('producto_solicitud')->where('solicitud_id', $solicitud->id)->delete();
        foreach ($productos as $item) {
            $precio = Producto::whereKey($item['producto_id'])->value('precio');
            DB::table('producto_solicitud')->insert([
                'producto_id' => $item['producto_id'],
                'solicitud_id' => $solicitud->id,
                'cantidad' => $item['cantidad'] ?? 1,
                'precio_unitario' => $precio,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => Auth::id(),
            ]);
        }
        $solicitud->unsetRelation('productos');
    }

    /** Asignación inactiva: se activa al aceptar la solicitud */
    private function guardarAsignacion(Solicitud $solicitud, array $d): void
    {
        $vehiculo = Vehiculo::findOrFail($d['vehiculo_id']);
        [$inicio, $fin] = PoliticaReserva::intervaloComprometido(
            CarbonImmutable::parse($d['traslado_at'])->utc(),
            $this->parametros->entero('presentacion_previa_minutos'),
            $this->parametros->entero('duracion_traslado_horas'),
        );
        $asignacion = Asignacion::updateOrCreate(['solicitud_id' => $solicitud->id], [
            'vehiculo_id' => $vehiculo->id,
            'externo' => $vehiculo->origen === 'externo',
            'inicio' => $inicio,
            'fin' => $fin,
            'activa' => false,
        ]);
        DB::table('asignacion_usuario')->where('asignacion_id', $asignacion->id)->delete();
        foreach (array_values(array_unique($d['equipo'])) as $i => $usuarioId) {
            DB::table('asignacion_usuario')->insert([
                'asignacion_id' => $asignacion->id,
                'usuario_id' => $usuarioId,
                'funcion' => $i === 0 ? 'conductor' : 'apoyo',
                'inicio' => $inicio,
                'fin' => $fin,
                'activa' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => Auth::id(),
            ]);
        }
    }

    private function equipoIds(int $solicitudId): array
    {
        return DB::table('asignacion_usuario')->join('asignaciones', 'asignaciones.id', '=', 'asignacion_usuario.asignacion_id')
            ->where('asignaciones.solicitud_id', $solicitudId)->pluck('asignacion_usuario.usuario_id')->all();
    }
}
