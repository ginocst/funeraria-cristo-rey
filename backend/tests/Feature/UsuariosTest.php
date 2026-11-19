<?php

use App\Modules\Identidad\Infrastructure\Models\Sucursal;

function datosUsuario(array $cambios = []): array
{
    return [
        'nombre' => 'Persona de prueba',
        'email' => uniqid() . '@prueba.test',
        'rut' => random_int(10_000_000, 99_999_999) . '-9',
        'sucursal_id' => Sucursal::firstOrCreate(['nombre' => 'Temuco'])->id,
        'rol' => 'Operario',
        'password' => 'Clave-Segura-2026',
        ...$cambios,
    ];
}

it('exige el RUT al crear un usuario', function () {
    $admin = $this->usuario('Administrador');

    foreach ([null, ''] as $vacio) {
        $this->actingAs($admin)->postJson('/api/usuarios', datosUsuario(['rut' => $vacio]))
            ->assertUnprocessable()->assertJsonValidationErrors('rut');
    }
});

it('no permite dos usuarios con el mismo RUT, aunque se escriba distinto', function () {
    $admin = $this->usuario('Administrador');

    $this->actingAs($admin)->postJson('/api/usuarios', datosUsuario(['rut' => '12.345.678-5']))
        ->assertCreated()->assertJsonPath('rut', '12345678-5');

    $this->actingAs($admin)->postJson('/api/usuarios', datosUsuario(['rut' => '12345678-5']))
        ->assertUnprocessable()->assertJsonValidationErrors('rut');
});

it('permite editar un usuario conservando su propio RUT', function () {
    $admin = $this->usuario('Administrador');

    $id = $this->actingAs($admin)->postJson('/api/usuarios', datosUsuario(['rut' => '11222333-4']))->json('id');
    $this->actingAs($admin)->putJson("/api/usuarios/$id", datosUsuario(['rut' => '11.222.333-4', 'nombre' => 'Otro nombre']))->assertOk();
});
