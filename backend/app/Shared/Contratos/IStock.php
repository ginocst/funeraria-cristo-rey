<?php

namespace App\Shared\Contratos;

/** Interfaz que ofrece Inventario a Solicitudes de reserva (IStock) */
interface IStock
{
    /**
     * Bloquea las filas de los productos (SELECT … FOR UPDATE), descuenta el
     * stock y registra los movimientos. Lanza ReglaNegocioException si no alcanza.
     *
     * @param  array<int, int>  $items  producto_id => cantidad
     */
    public function reservar(int $solicitudId, array $items): void;

    /**
     * Comprueba que hoy alcance el stock, sin reservarlo. Lanza ReglaNegocioException si no.
     *
     * @param  array<int, int>  $items  producto_id => cantidad
     */
    public function verificarDisponible(array $items): void;

    /** Repone el stock reservado por una solicitud (cancelación o cambio de urna) */
    public function liberar(int $solicitudId, array $items, string $motivo): void;
}
