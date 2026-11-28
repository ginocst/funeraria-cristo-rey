<?php

use Illuminate\Support\Facades\DB;
use Tests\Feature\Escenario;

beforeEach(function () {
    $this->admin = $this->usuario('Administrador');
    $this->operario = $this->usuario('Operario');
    $this->base = Escenario::base();
});

function crearSolicitud($test, int $expediente, array $equipo, string $traslado = '+3 days 15:00'): int
{
    return $test->actingAs($test->admin)
        ->postJson('/api/solicitudes', Escenario::datosSolicitud($expediente, $test->base, $equipo, $traslado))
        ->assertCreated()->json('id');
}

it('registra la solicitud en revisión sin comprometer stock ni vehículo', function () {
    $exp = Escenario::expediente($this->base['sucursal']);
    $id = crearSolicitud($this, $exp, [$this->operario->id]);

    expect(DB::table('solicitudes')->where('id', $id)->value('estado'))->toBe('en_revision')
        ->and(DB::table('productos')->where('id', $this->base['producto'])->value('stock'))->toBe(1)
        ->and(DB::table('asignaciones')->where('solicitud_id', $id)->value('activa'))->toBeFalse();
});

it('rechaza un traslado sin la anticipación mínima', function () {
    $exp = Escenario::expediente($this->base['sucursal']);
    $this->actingAs($this->admin)->postJson('/api/solicitudes', Escenario::datosSolicitud($exp, $this->base, [$this->operario->id], '+2 hours'))
        ->assertUnprocessable()->assertJsonPath('codigo', 'anticipacion');
});

it('no acepta sin el abono mínimo', function () {
    $exp = Escenario::expediente($this->base['sucursal']);
    $id = crearSolicitud($this, $exp, [$this->operario->id]);
    Escenario::pagar($exp, 100000);

    $this->actingAs($this->admin)->postJson("/api/solicitudes/$id/aceptar", [], ['Idempotency-Key' => 'k-1'])
        ->assertUnprocessable()->assertJsonPath('codigo', 'abono_minimo');
});

it('al aceptar descuenta stock, activa la asignación y es idempotente', function () {
    $exp = Escenario::expediente($this->base['sucursal']);
    $id = crearSolicitud($this, $exp, [$this->operario->id]);
    Escenario::pagar($exp, 600000);

    $primera = $this->actingAs($this->admin)->postJson("/api/solicitudes/$id/aceptar", [], ['Idempotency-Key' => 'k-2'])->assertOk();
    $this->actingAs($this->admin)->postJson("/api/solicitudes/$id/aceptar", [], ['Idempotency-Key' => 'k-2'])
        ->assertOk()->assertHeader('Idempotent-Replayed', 'true')->assertJson($primera->json());

    expect(DB::table('productos')->where('id', $this->base['producto'])->value('stock'))->toBe(0)
        ->and(DB::table('movimientos_stock')->where('solicitud_id', $id)->count())->toBe(1)
        ->and(DB::table('asignaciones')->where('solicitud_id', $id)->value('activa'))->toBeTrue()
        ->and(DB::table('notificaciones')->where('evento', 'solicitud.aceptada')->exists())->toBeTrue();
});

it('impide aceptar dos servicios con el mismo vehículo a la misma hora', function () {
    DB::table('productos')->where('id', $this->base['producto'])->update(['stock' => 5]);
    $otro = $this->usuario('Operario');
    $a = Escenario::expediente($this->base['sucursal']);
    $b = Escenario::expediente($this->base['sucursal']);
    $idA = crearSolicitud($this, $a, [$this->operario->id]);
    $idB = crearSolicitud($this, $b, [$otro->id], '+3 days 16:00');
    Escenario::pagar($a, 600000);
    Escenario::pagar($b, 600000);

    $this->actingAs($this->admin)->postJson("/api/solicitudes/$idA/aceptar", [], ['Idempotency-Key' => 'a'])->assertOk();
    $this->actingAs($this->admin)->postJson("/api/solicitudes/$idB/aceptar", [], ['Idempotency-Key' => 'b'])
        ->assertStatus(409)->assertJsonPath('codigo', 'traslape');

    // La transacción se revirtió completa: el stock de B no se descontó
    expect(DB::table('productos')->where('id', $this->base['producto'])->value('stock'))->toBe(4)
        ->and(DB::table('solicitudes')->where('id', $idB)->value('estado'))->toBe('en_revision');
});

it('al cancelar una aceptada repone el stock y libera la asignación', function () {
    $exp = Escenario::expediente($this->base['sucursal']);
    $id = crearSolicitud($this, $exp, [$this->operario->id]);
    Escenario::pagar($exp, 600000);
    $this->actingAs($this->admin)->postJson("/api/solicitudes/$id/aceptar", [], ['Idempotency-Key' => 'c'])->assertOk();

    $this->actingAs($this->admin)->postJson("/api/solicitudes/$id/cancelar", ['motivo' => 'La familia desistió'])->assertOk();

    expect(DB::table('productos')->where('id', $this->base['producto'])->value('stock'))->toBe(1)
        ->and(DB::table('asignaciones')->where('solicitud_id', $id)->value('activa'))->toBeFalse();
});

function productoDePrueba(string $tipo, int $stock = 5, int $precio = 10000): int
{
    return DB::table('productos')->insertGetId([
        'codigo' => 'P-'.uniqid(),
        'nombre' => "Producto $tipo",
        'tipo' => $tipo,
        'precio' => $precio,
        'stock' => $stock,
        'umbral_minimo' => 0,
        'publico' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function pedir($test, array $productos)
{
    $exp = Escenario::expediente($test->base['sucursal']);
    $datos = [...Escenario::datosSolicitud($exp, $test->base, [$test->operario->id]), 'productos' => $productos];

    return $test->actingAs($test->admin)->postJson('/api/solicitudes', $datos);
}

it('exige un ataúd o urna, y admite varios', function () {
    $flores = productoDePrueba('elemento');
    $cenizas = productoDePrueba('urna_cremacion');
    $ataud = $this->base['producto'];

    pedir($this, [['producto_id' => $flores, 'cantidad' => 1]])->assertUnprocessable()->assertJsonPath('codigo', 'sin_urna');
    // Cremación con las cenizas repartidas en varias urnas, además del ataúd
    pedir($this, [['producto_id' => $ataud], ['producto_id' => $cenizas, 'cantidad' => 3]])->assertCreated();
});

it('no registra una solicitud si el stock no alcanza', function () {
    $flores = productoDePrueba('elemento', stock: 2);

    pedir($this, [['producto_id' => $this->base['producto']], ['producto_id' => $flores, 'cantidad' => 3]])
        ->assertStatus(409)->assertJsonPath('codigo', 'sin_stock');

    DB::table('productos')->where('id', $this->base['producto'])->update(['stock' => 0]);
    pedir($this, [['producto_id' => $this->base['producto']]])->assertStatus(409)->assertJsonPath('codigo', 'sin_stock');

    expect(DB::table('solicitudes')->count())->toBe(0);
});

it('al editar conserva el precio pactado y usa el vigente solo en lo que se agrega', function () {
    $flores = productoDePrueba('elemento', precio: 10000);
    $exp = Escenario::expediente($this->base['sucursal']);
    $id = crearSolicitud($this, $exp, [$this->operario->id]);
    Escenario::pagar($exp, 600000);
    $this->actingAs($this->admin)->postJson("/api/solicitudes/$id/aceptar", [], ['Idempotency-Key' => 'p'])->assertOk();
    DB::table('productos')->where('id', $this->base['producto'])->update(['precio' => 900000]);

    // Aceptada y con stock 0: su propia unidad cuenta como disponible al editar
    $datos = Escenario::datosSolicitud($exp, $this->base, [$this->operario->id]);
    unset($datos['expediente_id']);
    $datos['productos'][] = ['producto_id' => $flores, 'cantidad' => 2];
    $this->actingAs($this->admin)->putJson("/api/solicitudes/$id", $datos)->assertOk();

    $precios = DB::table('producto_solicitud')->where('solicitud_id', $id)->pluck('precio_unitario', 'producto_id');
    expect((int) $precios[$this->base['producto']])->toBe(500000)
        ->and((int) $precios[$flores])->toBe(10000)
        ->and(DB::table('productos')->where('id', $this->base['producto'])->value('stock'))->toBe(0);
});
