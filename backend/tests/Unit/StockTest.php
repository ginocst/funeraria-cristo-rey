<?php

use App\Modules\Inventario\Domain\Stock;
use App\Shared\Domain\ReglaNegocioException;

it('descuenta cuando hay stock suficiente', function () {
    expect(Stock::descontar(3, 2))->toBe(1);
});

it('impide dejar el stock negativo', function () {
    Stock::descontar(1, 2, 'Imperial Roble');
})->throws(ReglaNegocioException::class, 'Stock insuficiente de Imperial Roble');

it('rechaza cantidades no positivas', function () {
    Stock::reponer(5, 0);
})->throws(ReglaNegocioException::class);

it('marca crítico cuando el stock llega al umbral', function () {
    expect(Stock::esCritico(2, 2))->toBeTrue()->and(Stock::esCritico(3, 2))->toBeFalse();
});
