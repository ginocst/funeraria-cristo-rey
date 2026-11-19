<?php

use Illuminate\Support\Facades\DB;
use Tests\Feature\Escenario;

beforeEach(function () {
    $this->admin = $this->usuario('Administrador');
    $this->base = Escenario::base();
    $this->medio = DB::table('medios_pago')->where('nombre', 'Efectivo')->value('id');
});

it('no registra pagos en un expediente sin solicitud (sin saldo)', function () {
    $exp = Escenario::expediente($this->base['sucursal']);
    $this->actingAs($this->admin)->postJson('/api/pagos', ['expediente_id' => $exp, 'monto' => 10000, 'medio_pago_id' => $this->medio], ['Idempotency-Key' => 'p0'])
        ->assertUnprocessable()->assertJsonPath('codigo', 'sin_saldo');
});

it('registra el abono con comprobante y lo anula con un movimiento compensatorio', function () {
    $operario = $this->usuario('Operario');
    $exp = Escenario::expediente($this->base['sucursal']);
    $this->actingAs($this->admin)->postJson('/api/solicitudes', Escenario::datosSolicitud($exp, $this->base, [$operario->id]))->assertCreated();

    $pago = $this->actingAs($this->admin)->postJson('/api/pagos', ['expediente_id' => $exp, 'monto' => 300000, 'medio_pago_id' => $this->medio], ['Idempotency-Key' => 'p1'])
        ->assertCreated()->json('pago');
    expect($pago['comprobante']['folio'])->toStartWith('C-');

    $this->actingAs($this->admin)->postJson("/api/pagos/{$pago['id']}/anular", ['motivo' => 'Monto digitado dos veces'])->assertCreated();

    $saldo = DB::table('vista_saldos_expediente')->where('expediente_id', $exp)->first();
    expect((int) $saldo->pagado)->toBe(0)->and((int) $saldo->saldo)->toBe(1000000)
        ->and(DB::table('pagos')->where('expediente_id', $exp)->count())->toBe(2);
});

it('la base rechaza editar un pago (libro inmutable)', function () {
    $exp = Escenario::expediente($this->base['sucursal']);
    Escenario::pagar($exp, 1000);
    expect(fn() => DB::table('pagos')->where('expediente_id', $exp)->update(['monto' => 1]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
