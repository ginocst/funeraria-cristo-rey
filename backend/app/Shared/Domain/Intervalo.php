<?php

namespace App\Shared\Domain;

use DateTimeImmutable;

/** Intervalo semiabierto [inicio, fin), igual que tsrange en PostgreSQL */
final class Intervalo
{
    public function __construct(public readonly DateTimeImmutable $inicio, public readonly DateTimeImmutable $fin)
    {
        if ($fin <= $inicio) {
            throw new ReglaNegocioException('El término debe ser posterior al inicio.', 'intervalo');
        }
    }

    public function traslapaCon(self $otro): bool
    {
        return $this->inicio < $otro->fin && $otro->inicio < $this->fin;
    }
}
