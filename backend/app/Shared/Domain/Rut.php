<?php

namespace App\Shared\Domain;

/**
 * RUT. Se valida el formato y se normaliza (cuerpo de 7 u 8 dígitos y dígito verificador 0-9 o K)
 */
final class Rut
{
    public static function normalizar(string $rut): string
    {
        $limpio = strtoupper(preg_replace('/[^0-9kK]/', '', $rut) ?? '');
        if (strlen($limpio) < 2) {
            return $limpio;
        }

        return substr($limpio, 0, -1) . '-' . substr($limpio, -1);
    }

    public static function formatoValido(string $rut): bool
    {
        // Solo dígitos, puntos opcionales, guion opcional y DV 0-9 o K.
        if (!preg_match('/^(\d{1,2}(\.\d{3}){2}|\d{7,8})-?[0-9kK]$/', trim($rut))) {
            return false;
        }

        return (bool) preg_match('/^\d{7,8}-[0-9K]$/', self::normalizar($rut));
    }

    /** 12345678-9 pasa a 12.345.678-9 */
    public static function formatear(string $rut): string
    {
        $n = self::normalizar($rut);
        [$cuerpo, $dv] = array_pad(explode('-', $n), 2, '');

        return number_format((int) $cuerpo, 0, ',', '.') . '-' . $dv;
    }
}
