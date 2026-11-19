<?php

use Tests\Feature\Escenario;

it('muestra precio pero no stock ni proveedor en el catálogo público', function () {
    Escenario::base();
    $respuesta = $this->getJson('/api/publico/catalogo')->assertOk();

    $producto = $respuesta->json('0');
    expect($producto)->toHaveKeys(['nombre', 'precio', 'foto_url'])
        ->and($producto)->not->toHaveKeys(['stock', 'umbral_minimo', 'proveedor_id', 'created_by']);
});

it('filtra y ordena el catálogo', function () {
    Escenario::base();
    $this->getJson('/api/publico/catalogo?tipo=ataud&orden=precio_asc&q=roble')->assertOk()->assertJsonPath('0.nombre', 'Imperial Roble');
});
