<?php

use App\Modules\Notificaciones\Application\ProcesarOutbox;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Escenario;

beforeEach(function () {
    $this->admin = $this->usuario('Administrador');
    $this->operario = $this->usuario('Operario');
});

function pedirDias($prueba, $usuario, int $dias = 3, string $cuando = '+10 days', string $motivo = 'Matrimonio de un familiar')
{
    return $prueba->actingAs($usuario)->postJson('/api/dias-libres', [
        'desde' => now('America/Santiago')->modify($cuando)->toDateString(),
        'dias' => $dias,
        'motivo' => $motivo,
    ]);
}

it('avisa al administrador cuando alguien pide días libres', function () {
    pedirDias($this, $this->operario)->assertCreated()->assertJsonPath('estado', 'pendiente');
    app(ProcesarOutbox::class)->lote();

    $aviso = DB::table('avisos')->where('usuario_id', $this->admin->id)->first();
    expect($aviso->titulo)->toBe('Solicitud de días libres')
        ->and($aviso->mensaje)->toContain('3 días')->toContain('Matrimonio de un familiar')
        ->and($aviso->enlace)->toBe('/dias-libres');
});

it('al aprobar menos días registra la ausencia por los aprobados y avisa a quien pidió', function () {
    $id = pedirDias($this, $this->operario, 5)->json('id');

    $this->actingAs($this->admin)->postJson("/api/dias-libres/$id/aprobar", ['dias' => 2])
        ->assertOk()->assertJsonPath('estado', 'aprobada')->assertJsonPath('dias_aprobados', 2);
    app(ProcesarOutbox::class)->lote();

    $ausencia = DB::table('ausencias')->where('usuario_id', $this->operario->id)->first();
    expect($ausencia->tipo)->toBe('permiso')
        ->and($ausencia->desde)->toBe(now('America/Santiago')->addDays(10)->toDateString())
        ->and($ausencia->hasta)->toBe(now('America/Santiago')->addDays(11)->toDateString())
        ->and(DB::table('avisos')->where('usuario_id', $this->operario->id)->value('mensaje'))->toContain('2 de 5');
});

it('no permite aprobar más días de los pedidos ni resolver dos veces', function () {
    $id = pedirDias($this, $this->operario, 2)->json('id');

    $this->actingAs($this->admin)->postJson("/api/dias-libres/$id/aprobar", ['dias' => 3])->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/api/dias-libres/$id/aprobar", ['dias' => 2])->assertOk();
    $this->actingAs($this->admin)->postJson("/api/dias-libres/$id/rechazar", ['motivo' => 'Cambio de planes'])
        ->assertStatus(409)->assertJsonPath('codigo', 'resuelta');
});

it('exige el motivo para rechazar y se lo comunica a quien pidió', function () {
    $id = pedirDias($this, $this->operario)->json('id');

    $this->actingAs($this->admin)->postJson("/api/dias-libres/$id/rechazar", [])->assertJsonValidationErrorFor('motivo');
    $this->actingAs($this->admin)->postJson("/api/dias-libres/$id/rechazar", ['motivo' => 'Semana con alta demanda'])
        ->assertOk()->assertJsonPath('estado', 'rechazada');
    app(ProcesarOutbox::class)->lote();

    expect(DB::table('avisos')->where('usuario_id', $this->operario->id)->value('mensaje'))->toContain('Semana con alta demanda')
        ->and(DB::table('ausencias')->count())->toBe(0);
});

it('cada persona ve solo sus solicitudes y no puede resolverlas', function () {
    $coordinador = $this->usuario('Coordinador');
    $id = pedirDias($this, $coordinador)->json('id');
    pedirDias($this, $this->operario)->assertCreated();

    $this->actingAs($this->operario)->getJson('/api/dias-libres')->assertOk()->assertJsonCount(1);
    $this->actingAs($this->admin)->getJson('/api/dias-libres')->assertOk()->assertJsonCount(2);
    $this->actingAs($this->operario)->postJson("/api/dias-libres/$id/aprobar", ['dias' => 1])->assertForbidden();
    $this->actingAs($coordinador)->postJson("/api/dias-libres/$id/rechazar", ['motivo' => 'No corresponde'])->assertForbidden();
});

it('el administrador no pide días y quien tiene la suplencia no resuelve los suyos', function () {
    pedirDias($this, $this->admin)->assertForbidden();

    $coordinador = $this->usuario('Coordinador');
    $id = pedirDias($this, $coordinador)->json('id');
    $this->actingAs($this->admin)->postJson("/api/usuarios/{$coordinador->id}/suplencia", ['hasta' => now()->addDays(3)->toDateString()])->assertOk();

    $this->actingAs($coordinador->fresh())->postJson("/api/dias-libres/$id/aprobar", ['dias' => 1])
        ->assertStatus(409)->assertJsonPath('codigo', 'propia');
});

it('rechaza fechas pasadas y solicitudes que se cruzan con otra pendiente', function () {
    pedirDias($this, $this->operario, 2, '-1 day')->assertJsonValidationErrorFor('desde');
    pedirDias($this, $this->operario, 5, '+10 days')->assertCreated();
    pedirDias($this, $this->operario, 1, '+14 days')->assertStatus(409)->assertJsonPath('codigo', 'pendiente');
    pedirDias($this, $this->operario, 1, '+15 days')->assertCreated();
});

it('no deja aprobar días en que la persona tiene un servicio confirmado', function () {
    $base = Escenario::base();
    $expediente = Escenario::expediente($base['sucursal']);
    $solicitud = $this->actingAs($this->admin)
        ->postJson('/api/solicitudes', Escenario::datosSolicitud($expediente, $base, [$this->operario->id], '+10 days 15:00'))
        ->assertCreated()->json('id');
    Escenario::pagar($expediente, 1_000_000);
    $this->actingAs($this->admin)->postJson("/api/solicitudes/$solicitud/aceptar", [], ['Idempotency-Key' => 'dias-1'])->assertOk();
    $id = pedirDias($this, $this->operario, 2, '+10 days')->json('id');

    $this->actingAs($this->admin)->postJson("/api/dias-libres/$id/aprobar", ['dias' => 2])
        ->assertStatus(409)->assertJsonPath('codigo', 'servicios');
    expect(DB::table('ausencias')->count())->toBe(0);
});
