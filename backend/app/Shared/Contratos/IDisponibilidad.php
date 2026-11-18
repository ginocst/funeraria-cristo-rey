<?php

namespace App\Shared\Contratos;

/** Interfaz que ofrece Flota y personal a Solicitudes de reserva (IDisponibilidad) */
interface IDisponibilidad
{
    /** Verifica ausencias y activa la asignación; PostgreSQL rechaza los traslapes (23P01) */
    public function activarAsignacion(int $solicitudId): void;

    public function liberarAsignacion(int $solicitudId): void;

    /** @return array<int, string> nombres de operarios con ausencia en el intervalo */
    public function operariosAusentes(array $usuarioIds, \DateTimeInterface $inicio, \DateTimeInterface $fin): array;
}