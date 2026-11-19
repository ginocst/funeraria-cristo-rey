<?php

namespace App\Modules\Finanzas\Application;

use App\Modules\Expedientes\Infrastructure\Models\Expediente;
use App\Modules\Finanzas\Domain\PlanCuotas;
use App\Modules\Finanzas\Domain\ReglasPago;
use App\Modules\Finanzas\Infrastructure\Models\Comprobante;
use App\Modules\Finanzas\Infrastructure\Models\Cuota;
use App\Modules\Finanzas\Infrastructure\Models\MedioPago;
use App\Modules\Finanzas\Infrastructure\Models\Pago;
use App\Shared\Contratos\IAuditoria;
use App\Shared\Contratos\IEventos;
use App\Shared\Contratos\IExpediente;
use App\Shared\Domain\ReglaNegocioException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Registrar pago, anular con movimiento compensatorio y pacto de cuotas. */
class GestionPagos
{
    public function __construct(private IExpediente $expedientes, private IAuditoria $auditoria, private IEventos $eventos) {}

    public function registrar(array $datos): Pago
    {
        return DB::transaction(function () use ($datos) {
            // Bloquea el expediente: dos pagos simultáneos no pueden superar el saldo
            $expediente = Expediente::whereKey($datos['expediente_id'])->lockForUpdate()->firstOrFail();
            if ($expediente->estado === 'anulado') {
                throw new ReglaNegocioException('El expediente está anulado.');
            }
            $medio = MedioPago::where('activo', true)->findOrFail($datos['medio_pago_id']);
            if ($medio->requiere_referencia && empty($datos['referencia'])) {
                throw new ReglaNegocioException("El medio «{$medio->nombre}» requiere un número de referencia.", 'referencia');
            }
            if (! empty($datos['cuota_id']) && ! Cuota::where('expediente_id', $expediente->id)->whereKey($datos['cuota_id'])->exists()) {
                throw new ReglaNegocioException('La cuota no pertenece a este expediente.');
            }
            ReglasPago::validarAbono((int) $datos['monto'], $this->expedientes->resumenFinanciero($expediente->id)['saldo']);

            $pago = Pago::create([
                'expediente_id' => $expediente->id,
                'cuota_id' => $datos['cuota_id'] ?? null,
                'medio_pago_id' => $medio->id,
                'tipo' => 'abono',
                'monto' => (int) $datos['monto'],
                'referencia' => $datos['referencia'] ?? null,
                'fecha' => now(),
            ]);
            Comprobante::create(['pago_id' => $pago->id]);
            $this->auditoria->registrar('registrar_pago', 'pagos', $pago->id, null, $pago->toArray());

            return $pago->load('comprobante', 'medioPago');
        });
    }

    public function anular(int $pagoId, string $motivo): Pago
    {
        return DB::transaction(function () use ($pagoId, $motivo) {
            $original = Pago::whereKey($pagoId)->lockForUpdate()->firstOrFail();
            if ($original->tipo !== 'abono') {
                throw new ReglaNegocioException('Solo se pueden anular abonos.');
            }
            if (Pago::where('anula_pago_id', $original->id)->exists()) {
                throw ReglaNegocioException::conflicto('Este pago ya fue anulado.', 'ya_anulado');
            }
            $anulacion = Pago::create([
                'expediente_id' => $original->expediente_id,
                'cuota_id' => $original->cuota_id,
                'medio_pago_id' => $original->medio_pago_id,
                'tipo' => 'anulacion',
                'monto' => -$original->monto,
                'anula_pago_id' => $original->id,
                'motivo_anulacion' => $motivo,
                'fecha' => now(),
            ]);
            Comprobante::create(['pago_id' => $anulacion->id]);
            $codigo = Expediente::whereKey($original->expediente_id)->value('codigo');
            $this->auditoria->registrar('anular_pago', 'pagos', $original->id, ['monto' => $original->monto], ['anulacion_id' => $anulacion->id], $motivo);
            $this->eventos->publicar('pago.anulado', ['expediente_id' => $original->expediente_id, 'codigo' => $codigo, 'motivo' => $motivo]);

            return $anulacion->load('comprobante');
        });
    }

    /** Pacta el saldo pendiente en cuotas mensuales (reemplaza las cuotas sin pagos) */
    public function pactarCuotas(int $expedienteId, int $numero, string $primerVencimiento): array
    {
        return DB::transaction(function () use ($expedienteId, $numero, $primerVencimiento) {
            Expediente::whereKey($expedienteId)->lockForUpdate()->firstOrFail();
            $conPagos = Pago::where('expediente_id', $expedienteId)->whereNotNull('cuota_id')->pluck('cuota_id')->unique();
            if ($conPagos->isNotEmpty()) {
                throw new ReglaNegocioException('Ya hay cuotas con pagos registrados; no se puede rehacer el plan.');
            }
            Cuota::where('expediente_id', $expedienteId)->get()->each->delete();
            $saldo = $this->expedientes->resumenFinanciero($expedienteId)['saldo'];
            $plan = PlanCuotas::generar($saldo, $numero, CarbonImmutable::parse($primerVencimiento));
            foreach ($plan as $c) {
                Cuota::create([...$c, 'expediente_id' => $expedienteId]);
            }
            $this->auditoria->registrar('pactar_cuotas', 'expedientes', $expedienteId, null, ['cuotas' => $plan]);

            return $plan;
        });
    }
}
