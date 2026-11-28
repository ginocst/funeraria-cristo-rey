<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Escenario;

const SALA = 'Sala Velatoria Principal, Calle Lautaro 195, Temuco';
const MATRIZ = 'Sala Velatoria Casa Matriz, Manuel Montt 1991, Población Santa Elena, Temuco';

beforeEach(function () {
    $this->admin = $this->usuario('Administrador');
    $this->base = Escenario::base();
    DB::table('productos')->where('id', $this->base['producto'])->update(['stock' => 20]);
    $this->operario = $this->usuario('Operario');
});

/** Solicitud con velatorio de 20 horas que termina con el traslado en $traslado */
function velar($prueba, string $lugar, string $traslado, array $cambios = [])
{
    $datos = Escenario::datosSolicitud(Escenario::expediente($prueba->base['sucursal']), $prueba->base, [$prueba->operario->id], $traslado);

    return $prueba->actingAs($prueba->admin)->postJson('/api/solicitudes', [...$datos, 'lugar_velatorio' => $lugar, ...$cambios]);
}

it('no permite dos solicitudes en la misma sala en horarios que se cruzan', function () {
    velar($this, SALA, '+10 days 15:00')->assertCreated();

    $rechazo = velar($this, '  calle lautaro 195,  TEMUCO ', '+10 days 20:00')->assertStatus(409)->assertJsonPath('codigo', 'sala_ocupada');
    expect($rechazo->json('message'))->toContain('EXP-');
    expect(DB::table('solicitudes')->count())->toBe(1);
});

it('permite la sala en otro horario, otra sala o un lugar externo', function () {
    velar($this, SALA, '+10 days 15:00')->assertCreated();

    // El velatorio siguiente puede empezar justo al salir el traslado anterior
    velar($this, SALA, '+11 days 11:00')->assertCreated();
    velar($this, MATRIZ, '+10 days 15:00')->assertCreated();
    velar($this, 'Domicilio particular', '+10 days 15:00')->assertCreated();
    velar($this, 'Domicilio particular', '+10 days 15:00')->assertCreated();
});

it('libera la sala al cancelar y respeta la regla al modificar', function () {
    $primera = velar($this, SALA, '+10 days 15:00')->json('id');
    $segunda = velar($this, SALA, '+20 days 15:00')->json('id');
    $datos = Escenario::datosSolicitud(1, $this->base, [$this->operario->id], '+10 days 16:00');
    unset($datos['expediente_id']);

    $this->putJson("/api/solicitudes/$segunda", [...$datos, 'lugar_velatorio' => SALA])->assertStatus(409)->assertJsonPath('codigo', 'sala_ocupada');
    // Modificar una solicitud no choca con su propia reserva
    $this->putJson("/api/solicitudes/$primera", [...$datos, 'lugar_velatorio' => SALA])->assertOk();
    $this->postJson("/api/solicitudes/$primera/cancelar", ['motivo' => 'La familia desistió'])->assertOk();
    $this->putJson("/api/solicitudes/$segunda", [...$datos, 'lugar_velatorio' => SALA])->assertOk();
});

it('la base de datos impide el cruce aunque se salte la aplicación', function () {
    $primera = velar($this, SALA, '+10 days 15:00')->json('id');
    $segunda = velar($this, 'Domicilio particular', '+10 days 15:00')->json('id');
    $sala = DB::table('solicitudes')->where('id', $primera)->value('sala_velatorio_id');

    expect(fn() => DB::table('solicitudes')->where('id', $segunda)->update(['sala_velatorio_id' => $sala]))
        ->toThrow(QueryException::class, 'solicitudes_sala_sin_traslape');
});

it('informa las salas ocupadas para el horario del formulario', function () {
    velar($this, SALA, '+10 days 15:00');
    $inicio = urlencode(now()->modify('+10 days 10:00')->toIso8601String());
    $traslado = urlencode(now()->modify('+10 days 18:00')->toIso8601String());

    $salas = collect($this->getJson("/api/disponibilidad?velatorio_inicio=$inicio&traslado_at=$traslado")->assertOk()->json('salas'))->keyBy('lugar');

    expect($salas[SALA]['ocupada_por'])->toStartWith('EXP-')
        ->and($salas[MATRIZ]['ocupada_por'])->toBeNull();
});

it('reconoce la sala por su nombre o dirección y deja escrito el lugar completo', function () {
    velar($this, 'sala velatoria principal', '+10 days 15:00')->assertCreated()->assertJsonPath('lugar_velatorio', SALA);
    velar($this, 'Sala Velatoria Cristo Rey, Padre Las Casas', '+10 days 15:00')->assertCreated()->assertJsonPath('sala_velatorio_id', null);
});

it('no deja sacar de uso una sala con servicios programados ni reservar una que no está disponible', function () {
    $solicitud = velar($this, SALA, '+10 days 15:00')->json('id');
    $sala = DB::table('salas_velatorias')->where('nombre', 'Sala Velatoria Principal')->value('id');

    $this->postJson("/api/salas-velatorias/$sala/estado", ['estado' => 'mantenimiento'])->assertStatus(409)->assertJsonPath('codigo', 'en_uso');
    $this->postJson("/api/solicitudes/$solicitud/cancelar", ['motivo' => 'La familia desistió'])->assertOk();
    $this->postJson("/api/salas-velatorias/$sala/estado", ['estado' => 'mantenimiento', 'observaciones' => 'Pintura'])
        ->assertOk()->assertJsonPath('estado', 'mantenimiento')->assertJsonPath('observaciones', 'Pintura');
    velar($this, SALA, '+30 days 15:00')->assertStatus(422)->assertJsonPath('codigo', 'sala_no_disponible');
    $this->postJson("/api/salas-velatorias/$sala/estado", ['estado' => 'disponible'])->assertOk();
    velar($this, SALA, '+30 days 15:00')->assertCreated();
});

it('muestra la agenda de cada sala y actualiza el lugar al cambiar sus datos', function () {
    velar($this, SALA, '+10 days 15:00');
    $agenda = collect($this->getJson('/api/salas-velatorias/agenda')->assertOk()->json())->keyBy('nombre');
    $sala = $agenda['Sala Velatoria Principal']['id'];

    expect($agenda['Sala Velatoria Principal']['reservas'])->toHaveCount(1)
        ->and($agenda['Sala Velatoria Casa Matriz']['reservas'])->toBe([]);
    $this->deleteJson("/api/salas-velatorias/$sala")->assertStatus(409);
    $this->putJson("/api/salas-velatorias/$sala", ['nombre' => 'Sala Lautaro', 'direccion' => 'Lautaro 195, Temuco', 'capacidad' => 50])->assertOk();
    expect(DB::table('solicitudes')->value('lugar_velatorio'))->toBe('Sala Lautaro, Lautaro 195, Temuco');
});

it('solo permite al administrador modificar las salas', function () {
    $this->actingAs($this->usuario('Coordinador'));

    $this->getJson('/api/salas-velatorias/agenda')->assertOk();
    $this->postJson('/api/salas-velatorias', ['nombre' => 'Otra', 'direccion' => 'Calle 1'])->assertForbidden();
    $this->postJson('/api/salas-velatorias/1/estado', ['estado' => 'mantenimiento'])->assertForbidden();
});
