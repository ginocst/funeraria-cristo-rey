<?php

use App\Shared\Domain\Rut;

it('normaliza puntos, guion y k minúscula', function () {
    expect(Rut::normalizar('12.345.678-k'))->toBe('12345678-K')
        ->and(Rut::normalizar(' 9.876.543-2 '))->toBe('9876543-2');
});

it('valida solo el formato, sin dígito verificador (módulo 11 omitido)', function () {
    expect(Rut::formatoValido('12.345.678-9'))->toBeTrue()
        ->and(Rut::formatoValido('12345678-0'))->toBeTrue()
        ->and(Rut::formatoValido('1234-5'))->toBeFalse()
        ->and(Rut::formatoValido('12.345.678-X'))->toBeFalse()
        ->and(Rut::formatoValido(''))->toBeFalse();
});

it('formatea con puntos para mostrar', function () {
    expect(Rut::formatear('12345678K'))->toBe('12.345.678-K');
});
