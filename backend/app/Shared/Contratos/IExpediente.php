<?php

namespace App\Shared\Contratos;

/** Interfaz que ofrece Expedientes de servicio (IExpediente) a Solicitudes y Finanzas */
interface IExpediente
{
    /** @return array{total:int, pagado:int, saldo:int} */
    public function resumenFinanciero(int $expedienteId): array;
}
