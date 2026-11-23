<?php

use App\Modules\Inventario\Infrastructure\Models\Producto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Escenario;

it('registra una merma: descuenta el stock y deja el movimiento con su motivo', function () {
    $base = Escenario::base();
    DB::table('productos')->where('id', $base['producto'])->update(['stock' => 3]);
    $coordinador = $this->usuario('Coordinador');

    $this->actingAs($coordinador)->postJson("/api/productos/{$base['producto']}/merma", ['cantidad' => 1, 'motivo' => 'Se quebró al moverla en la bodega'])
        ->assertOk()->assertJsonPath('stock', 2);

    $movimiento = DB::table('movimientos_stock')->where('producto_id', $base['producto'])->where('tipo', 'merma')->first();
    expect($movimiento->cantidad)->toBe(-1)
        ->and($movimiento->stock_resultante)->toBe(2)
        ->and($movimiento->motivo)->toBe('Se quebró al moverla en la bodega');
});

it('exige el motivo de la merma y no deja el stock negativo', function () {
    $base = Escenario::base();
    $admin = $this->usuario('Administrador');

    $this->actingAs($admin)->postJson("/api/productos/{$base['producto']}/merma", ['cantidad' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('motivo');
    $this->actingAs($admin)->postJson("/api/productos/{$base['producto']}/merma", ['cantidad' => 5, 'motivo' => 'Daño en el traslado'])
        ->assertStatus(409);

    expect(Producto::find($base['producto'])->stock)->toBe(1);
});

it('no permite al operario registrar mermas', function () {
    $base = Escenario::base();

    $this->actingAs($this->usuario('Operario'))->postJson("/api/productos/{$base['producto']}/merma", ['cantidad' => 1, 'motivo' => 'Daño en el traslado'])
        ->assertForbidden();
});

it('guarda largo, ancho y alto y los muestra en el catálogo público', function () {
    $admin = $this->usuario('Administrador');
    $id = $this->actingAs($admin)->postJson('/api/productos', [
        'codigo' => 'AT-900', 'nombre' => 'Modelo grande', 'tipo' => 'ataud', 'precio' => 900000, 'publico' => true,
        'largo_cm' => 220, 'ancho_cm' => 70, 'alto_cm' => 50,
    ])->assertCreated()->json('id');

    $publico = collect($this->getJson('/api/publico/catalogo')->assertOk()->json())->firstWhere('id', $id);
    expect($publico['largo_cm'])->toBe(220)
        ->and($publico['ancho_cm'])->toBe(70)
        ->and($publico['alto_cm'])->toBe(50)
        ->and($publico['medidas'])->toBe('220 × 70 × 50 cm');
});

it('rechaza medidas fuera de rango', function () {
    $admin = $this->usuario('Administrador');

    $this->actingAs($admin)->postJson('/api/productos', ['codigo' => 'AT-901', 'nombre' => 'Modelo', 'tipo' => 'ataud', 'precio' => 1, 'largo_cm' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('largo_cm');
    $this->actingAs($admin)->postJson('/api/productos', ['codigo' => 'AT-902', 'nombre' => 'Modelo', 'tipo' => 'ataud', 'precio' => 1, 'ancho_cm' => 401])
        ->assertUnprocessable()->assertJsonValidationErrors('ancho_cm');
});

it('agrega y quita fotos adicionales, y el catálogo entrega la galería en orden', function () {
    Storage::fake('catalogo');
    $base = Escenario::base();
    $admin = $this->usuario('Administrador');

    $foto = $this->actingAs($admin)->postJson("/api/productos/{$base['producto']}/fotos", [
        'foto' => UploadedFile::fake()->image('abierta.png', 800, 600),
        'vista' => 'abierta',
    ])->assertCreated()->json();

    $galeria = collect($this->getJson('/api/publico/catalogo')->json())->firstWhere('id', $base['producto'])['galeria'];
    expect($galeria)->toHaveCount(1)->and($galeria[0]['vista'])->toBe('abierta');

    $this->actingAs($admin)->deleteJson("/api/productos/{$base['producto']}/fotos/{$foto['id']}")->assertNoContent();
    $galeria = collect($this->getJson('/api/publico/catalogo')->json())->firstWhere('id', $base['producto'])['galeria'];
    expect($galeria)->toBeEmpty();
});

it('rechaza una vista de foto desconocida', function () {
    $base = Escenario::base();

    $this->actingAs($this->usuario('Administrador'))->postJson("/api/productos/{$base['producto']}/fotos", [
        'foto' => UploadedFile::fake()->image('a.png'),
        'vista' => 'techo',
    ])->assertUnprocessable()->assertJsonValidationErrors('vista');
});
