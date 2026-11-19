<?php

namespace App\Modules\Expedientes\Infrastructure;

use App\Shared\Contratos\IExpediente;
use Illuminate\Support\Facades\DB;

/** IExpediente: total contratado, pagado y saldo (vista_saldos_expediente) */
class ServicioExpediente implements IExpediente
{
    public function resumenFinanciero(int $expedienteId): array
    {
        $fila = DB::table('vista_saldos_expediente')->where('expediente_id', $expedienteId)->first();

        return [
            'total' => (int) ($fila->total ?? 0),
            'pagado' => (int) ($fila->pagado ?? 0),
            'saldo' => (int) ($fila->saldo ?? 0),
        ];
    }
}
