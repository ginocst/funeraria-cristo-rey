<?php

namespace App\Shared\Domain;

use RuntimeException;

/**
 * Error de una regla de negocio. Se devuelve al usuario con un mensaje claro
 * (422 si los datos no cumplen la regla; 409 si el estado o los recursos lo impiden)
 */
class ReglaNegocioException extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $codigo = 'regla', public readonly int $estado = 422)
    {
        parent::__construct($mensaje);
    }

    public static function conflicto(string $mensaje, string $codigo = 'conflicto'): self
    {
        return new self($mensaje, $codigo, 409);
    }
}
